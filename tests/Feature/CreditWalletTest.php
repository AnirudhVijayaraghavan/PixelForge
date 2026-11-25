<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Actions\Generations\ProcessPixelforgeWebhook;
use App\Domain\Billing\TokenUsage;
use App\Domain\Billing\WalletAccounting;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Integrations\Pixelforge\PixelforgeWebhookData;
use App\Jobs\ReconcileGeneration;
use App\Jobs\SubmitGeneration;
use App\Models\CreditWallet;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

function walletIntent(): string
{
    return (string) Str::uuid();
}

function walletInput(): array
{
    return ['prompt' => "\u{1F3A8}", 'quality' => 'low', 'size' => '1024x1024', 'reference_images' => []];
}

function walletRates(string $textRate): array
{
    return ['input_text_tokens' => $textRate, 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => '0'];
}

function stubWalletPricing(): void
{
    Queue::fake([SubmitGeneration::class]);
    Http::fake(['*/pricing' => Http::response(array_replace(pixelforgePricingFixture(), ['rates' => walletRates('2000000')]))]);
}

function postWalletIntent(User $user, ?string $intent = null, ?array $body = null): TestResponse
{
    return test()->actingAs($user)->postJson(route('generations.store'), $body ?? walletInput(), ['Idempotency-Key' => $intent ?? walletIntent()]);
}

function fundedWalletUser(string $pack = '5'): User
{
    $user = User::factory()->create();
    app(WalletAccounting::class)->purchase($user, $pack, walletIntent());

    return $user;
}

function walletGeneration(User $user): Generation
{
    return Generation::query()->findOrFail(postWalletIntent($user)->assertAccepted()->json('id'));
}

function applyWalletSnapshot(Generation $generation, GenerationStatus $state, array $changes = []): void
{
    app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, $state, $changes)));
}

function assertWalletFacts(User $user, int $settled, int $reserved): void
{
    $wallet = CreditWallet::query()->findOrFail($user->id);
    $ledger = DB::table('credit_ledger_entries')->where('user_id', $user->id);
    $credits = (clone $ledger)->whereNotNull('purchase_intent')->sum('amount');
    $debits = (clone $ledger)->whereNotNull('generation_id')->sum('amount');
    $holds = DB::table('credit_reservations')->where('state', 'active')->where('user_id', $user->id)->sum('held');
    expect($wallet->settled)->toBe($settled)->toBe($credits - $debits)
        ->and($wallet->reserved)->toBe($reserved)->toBe($holds)
        ->and($wallet->available()->nanodollars())->toBe($settled - $reserved);
}

test('registration and first access to an existing account create exactly one empty wallet each', function () {
    $this->post('/register', ['name' => 'Artist', 'email' => 'artist@example.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect('/studio');
    $registered = User::where('email', 'artist@example.test')->firstOrFail();
    assertWalletFacts($registered, 0, 0);
    $existing = User::factory()->create();
    foreach ([1, 2] as $visit) {
        $this->actingAs($existing)->get(route('wallet.index'))->assertInertia(fn (Assert $page) => $page->component('wallet/Index')->where('balances.available', '0.000000000')->has('activity.data', 0));
    }
    $this->assertDatabaseCount('credit_wallets', 2);
});

test('simulated purchase is scoped confirmed denomination validated and replay safe', function () {
    $user = User::factory()->create();
    $other = User::factory()->admin()->create();
    $url = route('wallet.purchase');
    $body = ['denomination' => '10', 'intent' => walletIntent(), 'confirmed' => true];
    $this->get(route('wallet.index'))->assertRedirect(route('login'));
    $this->postJson($url, $body)->assertUnauthorized();
    $this->actingAs($user);
    foreach ([['denomination' => '0.01'], ['confirmed' => false], ['intent' => 'bad'], ['user_id' => $other->id], ['amount' => '999']] as $change) {
        $this->postJson($url, array_replace($body, $change))->assertUnprocessable();
    }
    foreach ([1, 2] as $attempt) {
        $this->post($url, $body + ['balance' => '999999'])->assertRedirect(route('wallet.index'));
    }
    $this->postJson($url, array_replace($body, ['denomination' => '25']))->assertUnprocessable();
    assertWalletFacts($user, 10000000000, 0);
    $this->actingAs($other)->get(route('wallet.index', ['user_id' => $user->id]))->assertInertia(fn (Assert $page) => $page->has('activity.data', 0));
    $this->postJson($url, $body + ['user_id' => $user->id])->assertUnprocessable();
    $this->patchJson('/wallet/purchases/'.$body['intent'], ['amount' => 0])->assertNotFound();
    assertWalletFacts($other, 0, 0);
    $this->assertDatabaseCount('credit_ledger_entries', 1);
});

test('ledger rejects direct SQL edits or deletion', function (string $operation) {
    fundedWalletUser();
    $ledger = DB::table('credit_ledger_entries');
    expect(fn () => $operation === 'delete' ? $ledger->delete() : $ledger->update(['amount' => 42]))->toThrow(QueryException::class);
})->with(['delete', 'update']);

test('admission serializes distinct intents at the maximum and replays identical inputs once', function () {
    stubWalletPricing();
    $user = fundedWalletUser();
    $intent = walletIntent();
    $id = postWalletIntent($user, $intent)->assertAccepted()->json('id');
    postWalletIntent($user, $intent)->assertAccepted()->assertJsonPath('id', $id);
    postWalletIntent($user, $intent, array_replace(walletInput(), ['quality' => 'high']))->assertUnprocessable();
    postWalletIntent($user)->assertJsonValidationErrors('wallet');
    Queue::assertPushed(SubmitGeneration::class, 1);
    $this->assertDatabaseCount('credit_reservations', 1);
    assertWalletFacts($user, 5000000000, 5000000000);
    app(WalletAccounting::class)->purchase($user, '5', walletIntent());
    walletGeneration($user);
    assertWalletFacts($user, 10000000000, 10000000000);
});

test('one nanodollar below the maximum blocks admission even when the estimate is affordable', function () {
    stubWalletPricing();
    $user = fundedWalletUser();
    $g = walletGeneration($user);
    applyWalletSnapshot($g, GenerationStatus::Succeeded, ['rates' => walletRates('0.001')]);
    assertWalletFacts($user, 4999999999, 0);
    $this->actingAs($user)->postJson(route('generations.quote'), walletInput())->assertOk()
        ->assertJsonPath('estimate.customer_charge_usd', '2.500000000')->assertJsonPath('maximum.customer_charge_usd', '5.000000000')
        ->assertJsonPath('available_usd', '4.999999999')->assertJsonPath('affordable', false);
    postWalletIntent($user)->assertJsonValidationErrors('wallet');
    $this->assertDatabaseCount('generations', 1);
});

test('accepted rate increase expands a hold when funded and records a visible deficit otherwise', function (string $pack, int $expectedHold, int $expectedSettled) {
    stubWalletPricing();
    $user = fundedWalletUser($pack);
    $g = walletGeneration($user);
    $rates = walletRates('4000000');
    applyWalletSnapshot($g, GenerationStatus::Queued, ['rates' => $rates]);
    $hold = $g->reservation()->firstOrFail();
    expect($hold->accepted_maximum)->toBe(10000000000)->and($hold->held)->toBe($expectedHold);
    applyWalletSnapshot($g, GenerationStatus::Succeeded, ['rates' => $rates, 'usage' => (new TokenUsage(2, 0, 280, 0))->toArray()]);
    assertWalletFacts($user, $expectedSettled, 0);
    expect($g->settlement()->firstOrFail()->customer_charge_nanodollars)->toBe('10000000000');
    expect($hold->fresh()->state)->toBe('captured');
})->with([['10', 10000000000, 0], ['5', 5000000000, -5000000000]]);

test('successful webhook before POST response and duplicate deliveries debit one financial fact', function () {
    stubWalletPricing();
    $user = fundedWalletUser();
    $g = walletGeneration($user);
    $event = PixelforgeWebhookFixture($g, GenerationStatus::Succeeded);
    $event['data']['object']['rates'] = walletRates('2000000');
    $processor = app(ProcessPixelforgeWebhook::class);
    $dto = PixelforgeWebhookData::fromArray($event);
    expect($processor->handle($dto))->toBeTrue()->and($processor->handle($dto))->toBeFalse();
    $event['id'] = 'evt_'.Str::ulid();
    $processor->handle(PixelforgeWebhookData::fromArray($event));
    applyWalletSnapshot($g->fresh(), GenerationStatus::Queued);
    applyWalletSnapshot($g->fresh(), GenerationStatus::Failed);
    $this->assertDatabaseCount('generation_settlements', 1);
    assertWalletFacts($user, 2500000000, 0);
    $this->assertDatabaseCount('credit_ledger_entries', 2);
});

test('failed snapshot releases full hold and stale successes cannot debit', function () {
    stubWalletPricing();
    $user = fundedWalletUser();
    $g = walletGeneration($user);
    applyWalletSnapshot($g, GenerationStatus::Failed);
    applyWalletSnapshot($g->fresh(), GenerationStatus::Succeeded);
    assertWalletFacts($user, 5000000000, 0);
    expect($g->reservation()->firstOrFail()->state)->toBe('released');
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('submission releases only definitive final rejections', function (string $error, int $status, int $attempts, string $expected) {
    stubWalletPricing();
    config(['services.pixelforge.api_key' => 'test-only']);
    $user = fundedWalletUser();
    $g = walletGeneration($user);
    $g->submission_attempts = $attempts;
    $g->save();
    Http::fake(['*/generations' => $error === 'timeout' ? Http::failedConnection() : Http::response(pixelforgeErrorFixture($error), $status)]);
    $job = (new SubmitGeneration($g->id))->withFakeQueueInteractions();
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
    expect($g->reservation()->firstOrFail()->state)->toBe($expected);
    assertWalletFacts($user, 5000000000, $expected === 'active' ? 5000000000 : 0);
})->with([
    ['content_policy_violation', 400, 0, 'released'], ['validation_failed', 422, 0, 'released'],
    ['overloaded', 503, 0, 'active'], ['rate_limit_exceeded', 429, 0, 'active'], ['overloaded', 503, 4, 'released'],
    ['daily_limit_exceeded', 429, 0, 'active'], ['timeout', 0, 4, 'active'],
]);

test('worker death preserves credits and polling a missed completion captures the authoritative amount', function () {
    stubWalletPricing();
    config(['services.pixelforge.api_key' => 'test-only']);
    $user = fundedWalletUser();
    $g = walletGeneration($user);
    $g->submission_status = SubmissionStatus::Submitting;
    $g->save();
    (new SubmitGeneration($g->id))->failed(null);
    assertWalletFacts($user, 5000000000, 5000000000);
    applyWalletSnapshot($g, GenerationStatus::Processing);
    $g->refresh();
    $this->travel(3)->minutes();
    Http::fake(['*/generations/*' => Http::response(pixelforgeGenerationFixture($g, GenerationStatus::Succeeded))]);
    (new ReconcileGeneration($g->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
    $charge = (int) $g->settlement()->firstOrFail()->customer_charge_nanodollars;
    assertWalletFacts($user, 5000000000 - $charge, 0);
});

test('a pre-wallet in-flight generation settles without any wallet mutation', function () {
    $user = fundedWalletUser();
    $g = Generation::factory()->for($user)->create();
    applyWalletSnapshot($g, GenerationStatus::Succeeded);
    applyWalletSnapshot($g->fresh(), GenerationStatus::Succeeded);
    expect($g->settlement()->count())->toBe(1);
    assertWalletFacts($user, 5000000000, 0);
    $this->assertDatabaseCount('credit_reservations', 0);
    $this->assertDatabaseCount('credit_ledger_entries', 1);
});

test('a database failure during capture rolls back event, settlement, debit and reservation together', function () {
    stubWalletPricing();
    $user = fundedWalletUser();
    $g = walletGeneration($user);
    $event = PixelforgeWebhookData::fromArray(pixelforgeWebhookFixture($g));
    $processor = app(ProcessPixelforgeWebhook::class);
    DB::statement("CREATE TRIGGER abort_wallet_close BEFORE UPDATE ON credit_reservations WHEN NEW.state='captured' BEGIN SELECT RAISE(ABORT,'simulated failure'); END");
    expect(fn () => $processor->handle($event))->toThrow(QueryException::class);
    $this->assertDatabaseCount('pixelforge_webhook_events', 0);
    $this->assertDatabaseCount('generation_settlements', 0);
    expect($g->fresh()->provider_generation_id)->toBeNull();
    assertWalletFacts($user, 5000000000, 5000000000);
    DB::statement('DROP TRIGGER abort_wallet_close');
    expect($processor->handle($event))->toBeTrue();
    $charge = (int) $g->settlement()->firstOrFail()->customer_charge_nanodollars;
    assertWalletFacts($user, 5000000000 - $charge, 0);
});

test('a capture cannot disagree with its settlement or be charged to another wallet', function (bool $wrongOwner) {
    stubWalletPricing();
    $user = fundedWalletUser();
    $other = fundedWalletUser();
    $g = walletGeneration($user);
    $snapshot = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($g, GenerationStatus::Succeeded));
    // Attempt an inconsistent insert after the settlement exists and before the real capture.
    GenerationSettlement::created(function ($settlement) use ($wrongOwner, $user, $other, $g): void {
        DB::table('credit_ledger_entries')->insert(['id' => walletIntent(), 'user_id' => $wrongOwner ? $other->id : $user->id, 'generation_id' => $g->id,
            'amount' => (int) $settlement->customer_charge_nanodollars + ($wrongOwner ? 0 : 1), 'created_at' => now()]);
    });
    expect(fn () => app(ApplyGenerationSnapshot::class)->handle($g->id, $snapshot))->toThrow(QueryException::class);
    assertWalletFacts($user, 5000000000, 5000000000);
    assertWalletFacts($other, 5000000000, 0);
})->with([true, false]);

test('account closure preserves immutable wallet activity and the authenticated account', function () {
    $user = fundedWalletUser();
    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
    $this->assertAuthenticatedAs($user);
    assertWalletFacts($user, 5000000000, 0);
});
