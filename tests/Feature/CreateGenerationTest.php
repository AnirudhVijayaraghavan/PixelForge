<?php

use App\Actions\Generations\CreateGeneration;
use App\Domain\Billing\WalletAccounting;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

beforeEach(function () {
    $this->withHeader('Idempotency-Key', (string) Str::uuid());
});

function fundGenerationTestUser(User $user): User
{
    app(WalletAccounting::class)->purchase($user, '5', (string) Str::uuid());

    return $user;
}

/** @return array{prompt: string, size: string, quality: string, reference_images: list<string>} */
function validGenerationInput(): array
{
    return [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/1.png', 'https://example.com/2.png'],
    ];
}

test('denies unauthenticated JSON creation before fetching provider pricing', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);

    $this->postJson(route('generations.store'), validGenerationInput())->assertUnauthorized();

    Http::assertNothingSent();
    $this->assertDatabaseCount('generations', 0);
});

test('persists an authorized server quote and immutable payload before dispatching submission', function (bool $verified) {
    Queue::fake([SubmitGeneration::class]);
    $user = fundGenerationTestUser(User::factory()->create(['email_verified_at' => $verified ? now() : null]));
    $transactionLevel = DB::transactionLevel();
    Http::fake(function (Request $request) use ($transactionLevel) {
        expect(DB::transactionLevel())->toBe($transactionLevel);
        expect($request->method())->toBe('GET');

        return Http::response(pixelforgePricingFixture());
    });

    $response = $this->actingAs($user)->postJson(route('generations.store'), validGenerationInput());

    $response->assertAccepted()->assertJsonPath('submission_status', 'pending')
        ->assertJsonPath('estimated_customer_charge_usd', '0.412562500');
    $generation = Generation::query()->findOrFail($response->json('id'));
    expect($generation->user_id)->toBe($user->id);
    expect($generation->request_payload)->toBe(validGenerationInput() + ['metadata' => ['local_generation_id' => $generation->id]]);
    expect($generation->idempotency_key)->toBeUuid();
    expect($generation->provider_generation_id)->toBeNull();
    expect($generation->accepted_provider_rates)->toBeNull();
    expect($generation->maximum_customer_charge_nanodollars)->toBe('467343750');
    expect($generation->quote_snapshot['rates'])->toBe(pixelforgePricingFixture()['rates']);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generationId === $generation->id
        && $job->connection === 'database' && $job->queue === 'pixelforge' && $job->afterCommit === true);
    Queue::assertPushed(SubmitGeneration::class, 1);
    Http::assertSentCount(1);
})->with(['verified' => true, 'unverified' => false]);

test('enqueues on the database connection even when the global test queue is synchronous', function () {
    $user = fundGenerationTestUser(User::factory()->create());
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);

    $response = $this->actingAs($user)->postJson(route('generations.store'), validGenerationInput());

    $response->assertAccepted();
    expect(config('queue.default'))->toBe('sync');
    $this->assertDatabaseCount('jobs', 1);
    expect(DB::table('jobs')->first()->queue)->toBe('pixelforge');
    expect(DB::table('jobs')->first()->payload)->toContain($response->json('id'))->not->toContain(validGenerationInput()['prompt']);
    expect(Generation::query()->findOrFail($response->json('id'))->submission_attempts)->toBe(0);
    Http::assertSentCount(1);
});

test('checks the create policy explicitly', function () {
    Queue::fake([SubmitGeneration::class]);
    $user = User::factory()->create();
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'create' ? false : null);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);

    $this->actingAs($user)->postJson(route('generations.store'), validGenerationInput())->assertForbidden();

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('rejects unsupported inputs before pricing or persistence', function (array $changes, string $field) {
    $user = fundGenerationTestUser(User::factory()->create());
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);

    $this->actingAs($user)->postJson(route('generations.store'), array_replace(validGenerationInput(), $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    Http::assertNothingSent();
    $this->assertDatabaseCount('generations', 0);
})->with([
    'empty prompt' => [['prompt' => ''], 'prompt'],
    'too many Unicode characters' => [['prompt' => str_repeat('🎨', 4001)], 'prompt'],
    'wrong size' => [['size' => '512x512'], 'size'],
    'wrong quality' => [['quality' => 'ultra'], 'quality'],
    'five references' => [['reference_images' => array_fill(0, 5, 'https://example.com/a')], 'reference_images'],
    'non-HTTP reference' => [['reference_images' => ['file:///etc/passwd']], 'reference_images.0'],
    'malformed references' => [['reference_images' => 'https://example.com/a'], 'reference_images'],
    'non-list references' => [['reference_images' => ['named' => 'https://example.com/a']], 'reference_images'],
    'client price' => [['estimated_customer_charge_usd' => '0'], 'estimated_customer_charge_usd'],
    'client status' => [['provider_status' => 'succeeded'], 'provider_status'],
    'client provider ID' => [['provider_generation_id' => 'gen_fake'], 'provider_generation_id'],
    'client usage' => [['actual_token_usage' => []], 'actual_token_usage'],
    'client metadata' => [['metadata' => ['local_generation_id' => 'fake']], 'metadata'],
]);

test('accepts exactly 4000 Unicode characters and defaults omitted references to an empty list', function () {
    Queue::fake([SubmitGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);

    $response = $this->actingAs(fundGenerationTestUser(User::factory()->create()))->postJson(route('generations.store'), [
        'prompt' => str_repeat('🎨', 4000), 'size' => '1024x1024', 'quality' => 'low',
    ]);

    $response->assertAccepted();
    $generation = Generation::query()->findOrFail($response->json('id'));
    expect($generation->reference_images)->toBe([]);
    expect($generation->quote_snapshot['estimated_usage']['input_text_tokens'])->toBe(1000);
});

test('returns a safe unavailable response and persists nothing when pricing cannot be fetched', function () {
    Queue::fake([SubmitGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgeErrorFixture('overloaded'), 503)]);

    $this->actingAs(User::factory()->create())->postJson(route('generations.store'), validGenerationInput())
        ->assertServiceUnavailable()->assertJsonPath('code', 'pricing_unavailable');

    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
});

test('rolls back generation and after-commit dispatch together', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);
    $user = fundGenerationTestUser(User::factory()->create());

    try {
        DB::transaction(function () use ($user): void {
            app(CreateGeneration::class)->handle(
                $user, 'A lake', GenerationSize::Square,
                GenerationQuality::Low, [], (string) Str::uuid(),
            );
            throw new RuntimeException('Rollback the outer application transaction.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Rollback the outer application transaction.');
    }

    $this->assertDatabaseCount('generations', 0);
    $this->assertDatabaseCount('jobs', 0);
});

test('retains normal CSRF protection for the authenticated creation endpoint', function () {
    Queue::fake([SubmitGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);
    $middleware = new class(app(), app(Encrypter::class)) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };
    app()->instance(PreventRequestForgery::class, $middleware);

    $this->actingAs(User::factory()->create())->postJson(route('generations.store'), validGenerationInput())->assertStatus(419);

    Http::assertNothingSent();
    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
});
