<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

beforeEach(function () {
    $this->withoutVite();
    $this->freezeTime();
});

/** @param array<string, mixed> $changes */
function completedExperienceGeneration(User $user, array $changes = []): Generation
{
    $generation = Generation::factory()->for($user)->create();
    $snapshot = pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded, array_replace([
        'usage' => ['input_text_tokens' => 115, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000, 'total_tokens' => 16095],
    ], $changes));

    return app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray($snapshot));
}

test('protects every workspace read and quote route from guests', function (string $route, bool $json) {
    $generation = Generation::factory()->create();
    if ($route === 'generations.quote') {
        $this->postJson(route($route), [])->assertUnauthorized();
    } elseif ($json) {
        $this->getJson(route($route, ['ids' => [$generation->id]]))->assertUnauthorized();
    } else {
        $this->get(route($route, $route === 'generations.show' ? $generation : []))->assertRedirect(route('login'));
    }
    Http::assertNothingSent();
})->with([['studio', false], ['generations.index', false], ['generations.show', false], ['generations.statuses', true], ['generations.quote', true]]);

test('renders the studio and safely prefills only an owned generation without creating work', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->for($user)->create();
    $other = Generation::factory()->create();
    $this->actingAs($user)->get(route('studio'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('studio/Index')->where('inputs.prompt', '')->where('reusing', false));
    $this->get(route('studio', ['reuse' => $generation->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('inputs.prompt', $generation->prompt)->where('reusing', true)->missing('inputs.idempotency_key'));
    $this->get(route('studio', ['reuse' => $other->id]))->assertNotFound();
    $this->assertDatabaseCount('generations', 2);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertNothingSent();
});

test('paginates only owned history with deterministic newest-first ordering and bounded eager loading', function () {
    $user = User::factory()->create();
    $owned = Generation::factory()->count(14)->for($user)->create();
    Generation::factory()->count(3)->create();
    $expected = $owned->sortByDesc('id')->values();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($user)->get(route('generations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('generations/Index')
        ->has('generations.data', 12)->where('generations.total', 14)->where('generations.last_page', 2)->where('generations.data.0.id', $expected[0]->id)->where('generations.data.11.id', $expected[11]->id)
        ->missing('generations.data.0.request_payload')->missing('generations.data.0.provider_generation_id')->missing('generations.data.0.accepted_provider_rates'));
    $settlementQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'generation_settlements'));
    expect($settlementQueries)->toHaveCount(1);
    DB::disableQueryLog();
    $this->get(route('generations.index', ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('generations.data', 2)->where('generations.data.0.id', $expected[12]->id));
    $newest = Generation::factory()->for($user)->create(['created_at' => now()->addSecond()]);
    $this->get(route('generations.index'))->assertInertia(fn (Assert $page) => $page->where('generations.data.0.id', $newest->id));
    Http::assertNothingSent();
});

test('denies cross-user detail cost and status access without exposing partial results', function () {
    $user = User::factory()->create();
    $own = Generation::factory()->for($user)->create();
    $other = completedExperienceGeneration(User::factory()->create());
    $this->actingAs($user)->get(route('generations.show', $other))->assertNotFound();
    $this->getJson(route('generations.statuses', ['ids' => [$own->id, $other->id], 'detail' => 1]))->assertNotFound()->assertDontSee('0.467343750');
    Http::assertNothingSent();
});

test('unverified users can view their own history and exact billing details', function () {
    $user = User::factory()->unverified()->create();
    $generation = completedExperienceGeneration($user);

    $this->actingAs($user)->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('generations.data.0.id', $generation->id));

    $this->get(route('generations.show', $generation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generation.billing.customer_charge_usd', '0.467343750'));

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Http::assertNothingSent();
});

test('explicitly checks workspace collection policies', function () {
    $this->actingAs(User::factory()->create());
    Gate::before(fn (User $user, string $ability): ?bool => in_array($ability, ['viewAny', 'create'], true) ? false : null);
    $this->get(route('studio'))->assertForbidden();
    $this->get(route('generations.index'))->assertForbidden();
    $this->getJson(route('generations.statuses', ['ids' => []]))->assertForbidden();
    Http::assertNothingSent();
});

test('presents exact accepted usage and rates reconciled to the authoritative settlement', function (bool $doubleRates) {
    $user = User::factory()->create();
    $changes = $doubleRates ? ['rates' => ['input_text_tokens' => '10.00', 'input_image_tokens' => '20.00', 'output_tokens' => '80.00', 'reasoning_tokens' => '25.00']] : [];
    $generation = completedExperienceGeneration($user, $changes);
    $this->actingAs($user)->get(route('generations.show', $generation))->assertOk()->assertInertia(fn (Assert $page) => $page->component('generations/Show')
        ->where('generation.billing.state', 'settled')->has('generation.billing.rows', 4)->where('generation.billing.total_tokens', 16095)
        ->where('generation.billing.rows.0.tokens', 115)->where('generation.billing.rows.0.rate_usd', $doubleRates ? '10.00' : '5.00')
        ->where('generation.billing.rows.0.cost_usd', $doubleRates ? '0.001150000' : '0.000575000')
        ->where('generation.billing.provider_cost_usd', $doubleRates ? '0.747750000' : '0.373875000')
        ->where('generation.billing.customer_charge_usd', $doubleRates ? '0.934687500' : '0.467343750')
        ->where('generation.billing.markup_percentage', '25%')->where('generation.billing.markup_usd', $doubleRates ? '0.186937500' : '0.093468750')
        ->where('generation.billing.estimated_customer_charge_usd', '0.072225000')->where('generation.billing.difference_usd', $doubleRates ? '0.862462500' : '0.395118750')
        ->missing('generation.idempotency_key')->missing('generation.submission_error_code')->missing('generation.request_payload'));
    Http::assertNothingSent();
})->with([false, true]);

test('shows pending active and uncharged failure states without fabricating usage', function (string $state) {
    $user = User::factory()->create();
    $generation = match ($state) {
        'failed' => Generation::factory()->failed()->for($user)->create(['provider_error_message' => 'Sensitive internal diagnostics']),
        'rejected' => Generation::factory()->for($user)->create(['submission_status' => SubmissionStatus::Rejected, 'submission_error_message' => 'Sensitive internal diagnostics']),
        'uncertain' => Generation::factory()->for($user)->create(['submission_status' => SubmissionStatus::Uncertain, 'submission_error_message' => 'Sensitive internal diagnostics']),
        'queued' => Generation::factory()->accepted()->for($user)->create(),
        'processing' => Generation::factory()->accepted()->for($user)->create(['provider_status' => GenerationStatus::Processing]),
        default => Generation::factory()->for($user)->create(),
    };
    $free = in_array($state, ['failed', 'rejected'], true);
    $this->actingAs($user)->get(route('generations.show', $generation))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('generation.status', $state)->where('generation.billing.state', $free ? 'free' : 'pending')
        ->where('generation.billing.customer_charge_usd', $free ? '0.000000000' : null)->where('generation.billing.total_tokens', null)->where('generation.billing.rows', []))->assertDontSee('Sensitive');
    $this->getJson(route('generations.statuses', ['ids' => [$generation->id]]))->assertOk()->assertJsonPath('generations.0.status', $state)->assertJsonPath('generations.0.active', ! $free)
        ->assertJsonPath('generations.0.actual_customer_charge_usd', $free ? '0.000000000' : null)->assertDontSee('Sensitive');
    Http::assertNothingSent();
})->with(['pending', 'queued', 'processing', 'uncertain', 'rejected', 'failed']);

test('treats missing or inconsistent successful settlement data as unavailable rather than free', function (bool $missingSettlement) {
    $user = User::factory()->create();
    $generation = $missingSettlement ? Generation::factory()->succeeded()->for($user)->create() : completedExperienceGeneration($user);
    if (! $missingSettlement) {
        DB::table('generations')->where('id', $generation->id)->update(['actual_token_usage' => json_encode(['input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'total_tokens' => 0])]);
    }
    $this->actingAs($user)->get(route('generations.show', $generation))->assertOk()->assertInertia(fn (Assert $page) => $page->where('generation.billing.state', 'unavailable')->where('generation.billing.customer_charge_usd', $missingSettlement ? null : '0.467343750'));
    Http::assertNothingSent();
})->with([true, false]);

test('returns terminal status with exact costs and handles missing or expired image URLs gracefully', function (string $imageState) {
    $user = User::factory()->create();
    $generation = completedExperienceGeneration($user);
    if ($imageState === 'expired') {
        $this->travelTo($generation->image_url_expires_at->addSecond());
    } elseif ($imageState === 'unavailable') {
        $this->travelTo($generation->image_url_expires_at->subHour());
        DB::table('generations')->where('id', $generation->id)->update(['image_url' => null]);
    } else {
        $this->travelTo($generation->image_url_expires_at->subHour());
    }
    $this->actingAs($user)->getJson(route('generations.statuses', ['ids' => [$generation->id], 'detail' => 1]))->assertOk()
        ->assertJsonPath('generations.0.status', 'succeeded')->assertJsonPath('generations.0.active', false)
        ->assertJsonPath('generations.0.actual_customer_charge_usd', '0.467343750')->assertJsonPath('generations.0.image_state', $imageState)
        ->assertJsonPath('generations.0.image_url', $imageState === 'available' ? $generation->image_url : null)
        ->assertJsonPath('generations.0.billing.rows.3.cost_usd', '0.100000000');
    Http::assertNothingSent();
})->with(['available', 'expired', 'unavailable']);

test('bounds local polling identifiers and rejects malformed or duplicate IDs', function (string $case) {
    $generation = Generation::factory()->create();
    $ids = match ($case) {
        'empty' => [], 'invalid' => ['not-a-uuid'], 'duplicates' => [$generation->id, $generation->id], default => array_fill(0, 13, $generation->id)
    };
    $this->actingAs($generation->user)->getJson(route('generations.statuses', ['ids' => $ids]))->assertUnprocessable();
    Http::assertNothingSent();
})->with(['empty', 'invalid', 'duplicates', 'too many']);

test('presents settled free and pending amounts consistently in mixed history', function () {
    $user = User::factory()->create();
    $success = completedExperienceGeneration($user);
    $failed = Generation::factory()->failed()->for($user)->create();
    $uncertain = Generation::factory()->for($user)->create(['submission_status' => SubmissionStatus::Uncertain]);
    $response = $this->actingAs($user)->get(route('generations.index'))->assertOk();
    $response->assertInertia(function (Assert $page) use ($success, $failed, $uncertain): void {
        $page->has('generations.data', 3)->where('generations.data', function ($records) use ($success, $failed, $uncertain): bool {
            $records = collect($records)->keyBy('id');

            return $records[$success->id]['actual_customer_charge_usd'] === '0.467343750'
                && $records[$failed->id]['actual_customer_charge_usd'] === '0.000000000'
                && $records[$uncertain->id]['actual_customer_charge_usd'] === null;
        });
    });
    Http::assertNothingSent();
});

test('preserves prompt text while safely encoding the initial Inertia HTML', function () {
    $user = User::factory()->create();
    $prompt = '<script>window.__pixelforgeUnsafe = true</script>';
    $generation = Generation::factory()->for($user)->create(['prompt' => $prompt]);
    $this->actingAs($user)->get(route('generations.show', $generation))->assertOk()->assertDontSee($prompt, false)
        ->assertInertia(fn (Assert $page) => $page->where('generation.prompt', $prompt));
    Http::assertNothingSent();
});
