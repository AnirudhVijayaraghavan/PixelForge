<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Billing\FinancialReport;
use App\Domain\Billing\ProviderReconciliation;
use App\Domain\Billing\ReportPeriod;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo('2026-11-04 12:00:00 UTC');
    config(['services.pixelforge.api_key' => 'test-reporting-key']);
});

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

function reportingSettlement(User $user, string $date = '2026-11-01', string $provider = '57780000', string $customer = '72225000', array $rates = []): Generation
{
    $attributes = [
        'provider_completed_at' => $date.' 12:00:00',
        'created_at' => '2026-10-01 12:00:00',
    ];
    if ($rates !== []) {
        $attributes['accepted_provider_rates'] = $rates;
    }
    $generation = Generation::factory()->succeeded()->for($user)->create($attributes);
    GenerationSettlement::factory()->for($generation)->create([
        'provider_cost_nanodollars' => $provider, 'customer_charge_nanodollars' => $customer,
    ]);

    return $generation;
}

/** @return array<string, mixed> */
function reportingUsageFixture(array $changes = []): array
{
    $first = ['date' => '2026-11-01', 'generations' => 1, 'input_text_tokens' => 6, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 1100, 'cost_usd' => '0.057780000'];
    $zero = ['date' => '2026-11-02', 'generations' => 0, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.000000000'];
    $first = array_replace($first, $changes);

    return ['object' => 'usage', 'start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'data' => [$first, $zero], 'total' => array_diff_key($first, ['date' => true])];
}

test('protects every financial endpoint from guests and ordinary users', function (string $endpoint, bool $post) {
    $generation = Generation::factory()->create();
    $url = route($endpoint, $endpoint === 'admin.financials.show' ? $generation : []);
    if ($post) {
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
    } else {
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }
    Http::assertNothingSent();
})->with([['admin.financials.index', false], ['admin.financials.refresh', true], ['admin.financials.show', false]]);

test('aggregates actual settlements for daily user and daily-user reports without estimates or provider calls', function () {
    $admin = User::factory()->admin()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    reportingSettlement($first, '2026-11-01', '1', '1');
    reportingSettlement($first, '2026-11-01', '3', '4');
    reportingSettlement($second, '2026-11-02', '8', '10');
    reportingSettlement($first, '2026-10-31');
    Generation::factory()->failed()->for($first)->create(['provider_completed_at' => '2026-11-01 10:00:00']);

    $this->actingAs($admin)->get(route('admin.financials.index', ['start_date' => '2026-11-01', 'end_date' => '2026-11-02']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/financials/Index')
        ->where('report.summary.generations', '3')->where('report.summary.provider_cost_usd', '0.000000012')
        ->where('report.summary.customer_charge_usd', '0.000000015')->where('report.summary.gross_profit_usd', '0.000000003')
        ->where('report.daily.0.provider_cost_usd', '0.000000004')->where('report.daily.0.input_text_tokens', '12')
        ->where('report.daily.1.generations', '1')->has('report.users.data', 2)->has('report.breakdown.data', 2)
        ->where('report.generations.total', 4)->where('reconciliation.days.0.status', 'NOT_CHECKED')
        ->where('can.viewFinancialReports', true));
    Http::assertNothingSent();
});

test('keeps report accumulation exact above the signed BIGINT aggregate boundary', function () {
    $user = User::factory()->create();
    foreach (range(1, 3) as $ignored) {
        reportingSettlement($user, '2026-11-01', '4000000000000000001', '5000000000000000001');
    }
    $report = app(FinancialReport::class)->build(new ReportPeriod('2026-11-01', '2026-11-02'));

    expect($report['summary']['provider_cost_usd'])->toBe('12000000000.000000003');
    expect($report['summary']['customer_charge_usd'])->toBe('15000000000.000000003');
    expect($report['summary']['gross_profit_usd'])->toBe('3000000000.000000000');
    expect($report['users']['data'][0]['provider_cost_usd'])->toBe('12000000000.000000003');
});

test('uses UTC provider completion rather than creation or settlement date', function () {
    $user = User::factory()->create();
    $generation = reportingSettlement($user, '2026-11-01');
    DB::table('generations')->where('id', $generation->id)->update(['provider_completed_at' => '2026-11-01 23:59:59']);
    $next = reportingSettlement($user, '2026-11-02');
    DB::table('generations')->where('id', $next->id)->update(['provider_completed_at' => '2026-11-02 00:00:00']);

    $report = app(FinancialReport::class)->build(new ReportPeriod('2026-11-01', '2026-11-01'));
    expect($report['summary']['generations'])->toBe('1');
    expect($report['generations']['data'][0]['id'])->toBe($generation->id);
});

test('never reprices historical financial facts when pricing changes', function () {
    $user = User::factory()->create();
    reportingSettlement($user, '2026-11-01', '57780000', '72225000');
    reportingSettlement($user, '2026-11-02', '115560000', '144450000', [
        'input_text_tokens' => '10.00', 'input_image_tokens' => '20.00', 'output_tokens' => '80.00', 'reasoning_tokens' => '25.00',
    ]);
    config(['services.pixelforge.api_key' => null]);

    $report = app(FinancialReport::class)->build(new ReportPeriod('2026-11-01', '2026-11-02'));
    expect($report['summary']['provider_cost_usd'])->toBe('0.173340000');
    expect($report['summary']['customer_charge_usd'])->toBe('0.216675000');
    Http::assertNothingSent();
});

test('reports empty dates as zero locally and never checked remotely', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.financials.index', ['start_date' => '2026-11-01', 'end_date' => '2026-11-02']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.summary.provider_cost_usd', '0.000000000')
        ->where('report.summary.generations', '0')->has('report.daily', 2)->where('report.users.total', 0)
        ->where('reconciliation.days.1.status', 'NOT_CHECKED'));
});

test('flags missing successful settlements instead of displaying free success or matching', function () {
    Generation::factory()->succeeded()->create(['provider_completed_at' => '2026-11-01 00:00:00']);
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    $report = app(FinancialReport::class)->build($period);
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response(reportingUsageFixture(['generations' => 0, 'input_text_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.000000000']))]);
    app(ProviderReconciliation::class)->refresh($period);

    expect($report['summary']['missing_settlements'])->toBe(1);
    expect($report['generations']['data'][0]['customer_charge_usd'])->toBeNull();
    expect(app(ProviderReconciliation::class)->compare($period, $report['daily'])['days'][0]['status'])->toBe('MISMATCHED');
});

test('admin drill-down is explicitly authorized while ordinary detail remains owner-scoped', function () {
    $admin = User::factory()->admin()->create();
    $generation = reportingSettlement(User::factory()->create());
    $this->actingAs($admin)->get(route('admin.financials.show', $generation))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/financials/Show')
            ->where('generation.billing.customer_charge_usd', '0.072225000')
            ->where('provider_generation_id', $generation->provider_generation_id));
    $this->get(route('generations.show', $generation))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('admin.financials.show', $generation))->assertForbidden();
});

test('rejects invalid report ranges before any provider fetch', function (array $range) {
    $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.financials.refresh'), $range)->assertUnprocessable();
    Http::assertNothingSent();
})->with([
    [[]],
    [['start_date' => '2026-08-01', 'end_date' => '2026-11-01']],
    [['start_date' => '2026-11-02', 'end_date' => '2026-11-01']],
    [['start_date' => '2026-02-30', 'end_date' => '2026-03-01']],
]);

test('refreshes only on admin POST and matches every metric without creating financial records', function () {
    $generation = reportingSettlement(User::factory()->create());
    $admin = User::factory()->admin()->create();
    $range = ['start_date' => '2026-11-01', 'end_date' => '2026-11-02'];
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response(reportingUsageFixture())]);
    $this->actingAs($admin)->post(route('admin.financials.refresh'), $range)->assertRedirect(route('admin.financials.index', $range));
    $this->get(route('admin.financials.index', $range))->assertInertia(fn (Assert $page) => $page
        ->where('reconciliation.days.0.status', 'MATCHED')->where('reconciliation.days.1.status', 'MATCHED')
        ->where('reconciliation.days.0.differences.provider_cost_usd', '0.000000000')
        ->where('reconciliation.is_stale', false)->where('report.summary.generations', '1'));
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('generations', 1);
    expect($generation->fresh()->settlement->customer_charge_nanodollars)->toBe('72225000');
    Http::assertSentCount(1);
});

test('identifies exact reconciliation discrepancies including out-of-band provider generations', function (array $changes, string $metric, string $difference) {
    reportingSettlement(User::factory()->create());
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response(reportingUsageFixture($changes))]);
    app(ProviderReconciliation::class)->refresh($period);
    $report = app(FinancialReport::class)->build($period);
    $result = app(ProviderReconciliation::class)->compare($period, $report['daily']);

    expect($result['days'][0]['status'])->toBe('MISMATCHED');
    expect($result['days'][0]['differences'][$metric])->toBe($difference);
})->with([
    [['cost_usd' => '0.057780001'], 'provider_cost_usd', '-0.000000001'],
    [['input_text_tokens' => 7], 'input_text_tokens', '-1'],
    [['generations' => 2], 'generations', '-1'],
    [['generations' => 3, 'output_tokens' => 3300, 'cost_usd' => '0.173340000'], 'provider_cost_usd', '-0.115560000'],
]);

test('marks expired snapshots unavailable and retains their successful fetch timestamp', function () {
    reportingSettlement(User::factory()->create());
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response(reportingUsageFixture())]);
    app(ProviderReconciliation::class)->refresh($period);
    $this->travel(9)->minutes();
    $report = app(FinancialReport::class)->build($period);
    expect(app(ProviderReconciliation::class)->compare($period, $report['daily'])['status'])->toBe('MATCHED');
    $this->travel(1)->minutes();
    $result = app(ProviderReconciliation::class)->compare($period, $report['daily']);
    expect($result['is_stale'])->toBeTrue();
    expect($result['last_successful_fetch_at'])->not->toBeNull();
    expect($result['days'][0]['status'])->toBe('UNAVAILABLE');
    Http::assertSentCount(1);
});

test('a failed refresh preserves stale data but can never remain matched and respects Retry-After', function () {
    reportingSettlement(User::factory()->create());
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::sequence()->push(reportingUsageFixture())->push(['error' => ['type' => 'rate_limit_error', 'code' => 'rate_limit_exceeded', 'message' => 'Private diagnostics']], 429, ['Retry-After' => '120'])]);
    app(ProviderReconciliation::class)->refresh($period);
    $this->travel(61)->seconds();
    expect(app(ProviderReconciliation::class)->refresh($period))->not->toBeNull();
    $report = app(FinancialReport::class)->build($period);
    $result = app(ProviderReconciliation::class)->compare($period, $report['daily']);
    expect($result['days'][0]['status'])->toBe('UNAVAILABLE');
    expect($result['days'][0]['provider']['provider_cost_usd'])->toBe('0.057780000');
    expect($result['is_stale'])->toBeTrue();
    $this->travel(119)->seconds();
    expect(app(ProviderReconciliation::class)->refresh($period))->toContain('limited');
    Http::assertSentCount(2);
});

test('rate limits refreshes across ranges and isolates cached provider keys', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response(reportingUsageFixture())]);
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    app(ProviderReconciliation::class)->refresh($period);
    expect(app(ProviderReconciliation::class)->refresh(new ReportPeriod('2026-11-01', '2026-11-01')))->toContain('limited');
    config(['services.pixelforge.api_key' => 'a-different-provider-key']);
    $report = app(FinancialReport::class)->build($period);
    expect(app(ProviderReconciliation::class)->compare($period, $report['daily'])['days'][0]['status'])->toBe('NOT_CHECKED');
    Http::assertSentCount(1);
});

test('a first provider failure is unavailable rather than not checked or matched', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response(['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'private']], 503)]);
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    app(ProviderReconciliation::class)->refresh($period);
    $report = app(FinancialReport::class)->build($period);
    expect(app(ProviderReconciliation::class)->compare($period, $report['daily'])['days'][0]['status'])->toBe('UNAVAILABLE');
});

test('registration profile fields and mass assignment cannot grant admin access', function () {
    Notification::fake();
    $this->post(route('register.store'), ['name' => 'Ordinary User', 'email' => 'ordinary@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'is_admin' => true]);
    $user = User::query()->where('email', 'ordinary@example.com')->firstOrFail();
    expect($user->is_admin)->toBeFalse();
    expect(Gate::forUser($user)->allows('viewFinancialReports'))->toBeFalse();
    $this->patch(route('profile.update'), ['name' => 'Ordinary Updated', 'email' => $user->email, 'is_admin' => true])->assertSessionHasNoErrors();
    $user->fill(['is_admin' => true])->save();
    expect($user->fresh()->is_admin)->toBeFalse();
    $this->get(route('studio'))->assertInertia(fn (Assert $page) => $page->where('can.viewFinancialReports', false));
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('admin provisioning requires explicit console confirmation and supports revocation', function () {
    $user = User::factory()->create();
    $this->artisan('app:admin', ['user' => $user->id])->assertFailed();
    expect($user->fresh()->is_admin)->toBeFalse();
    $this->artisan('app:admin', ['user' => $user->id, '--force' => true])->assertSuccessful();
    expect($user->fresh()->can('viewFinancialReports'))->toBeTrue();
    $this->artisan('app:admin', ['user' => $user->id, '--force' => true, '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeFalse();
});

test('bounds user breakdown and drill-down pages without per-user queries', function () {
    foreach (User::factory()->count(21)->create() as $user) {
        reportingSettlement($user);
    }
    DB::enableQueryLog();
    $report = app(FinancialReport::class)->build(new ReportPeriod('2026-11-01', '2026-11-02'));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($report['users']['data'])->toHaveCount(15);
    expect($report['breakdown']['data'])->toHaveCount(15);
    expect($report['generations']['data'])->toHaveCount(20);
    expect($report['users']['total'])->toBe(21);
    expect(count($queries))->toBeLessThanOrEqual(12);
});

test('concurrent refresh lock prevents an additional provider request', function () {
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    $nested = null;
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => function () use (&$nested, $period) {
        if ($nested === null) {
            $nested = 'attempting';
            $nested = app(ProviderReconciliation::class)->refresh($period);
        }

        return Http::response(reportingUsageFixture());
    }]);

    expect(app(ProviderReconciliation::class)->refresh($period))->toBeNull();
    expect($nested)->toContain('already running');
    Http::assertSentCount(1);
});

test('scalar reconciliation snapshots survive serialized database caching', function () {
    reportingSettlement(User::factory()->create());
    $reconciliation = new ProviderReconciliation(app(PixelforgeClient::class), Cache::store('database'));
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response(reportingUsageFixture())]);
    $period = new ReportPeriod('2026-11-01', '2026-11-02');
    $reconciliation->refresh($period);
    $report = app(FinancialReport::class)->build($period);

    expect($reconciliation->compare($period, $report['daily'])['status'])->toBe('MATCHED');
    expect($reconciliation->compare($period, $report['daily'])['total_cost_difference_usd'])->toBe('0.000000000');
    Http::assertSentCount(1);
});

test('normalizes provider timezone offsets before grouping the UTC completion day', function () {
    $generation = Generation::factory()->create();
    $snapshot = pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded, [
        'created_at' => '2026-11-01T20:00:00Z', 'started_at' => '2026-11-01T20:01:00Z',
        'completed_at' => '2026-11-02T00:30:00+02:00',
    ]);
    app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray($snapshot));
    $report = app(FinancialReport::class)->build(new ReportPeriod('2026-11-01', '2026-11-02'));

    expect($report['daily'][0]['generations'])->toBe('1');
    expect($report['daily'][1]['generations'])->toBe('0');
});

test('paginates subsequent user and generation groups while preserving global exact totals', function () {
    foreach (User::factory()->count(21)->create() as $user) {
        reportingSettlement($user);
    }
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.financials.index', [
        'start_date' => '2026-11-01', 'end_date' => '2026-11-02',
        'users_page' => 2, 'breakdown_page' => 2, 'generations_page' => 2,
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('report.users.data', 6)->has('report.breakdown.data', 6)->has('report.generations.data', 1)
        ->where('report.users.current_page', 2)->where('report.summary.generations', '21')
        ->where('report.summary.provider_cost_usd', '1.213380000'));
});
