<?php

use App\Domain\Generations\Enums\GenerationStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeException;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

test('retrieves public pricing without credentials and briefly caches typed pricing data', function () {
    config(['services.pixelforge.api_key' => null]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);

    $pricing = app(PixelforgeClient::class)->pricing();
    $cached = app(PixelforgeClient::class)->pricing();

    expect($pricing->rates->toArray())->toBe(pixelforgePricingFixture()['rates']);
    expect($pricing->parameters->charactersPerTextToken)->toBe(4);
    expect($cached->rates->toArray())->toBe($pricing->rates->toArray());
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && ! $request->hasHeader('Authorization'));
    Http::assertSentCount(1);
});

test('submits the exact persisted JSON with bearer authentication stable identity and bounded timeouts', function () {
    config(['services.pixelforge.api_key' => 'test-only-key']);
    $generation = Generation::factory()->create();
    $fixture = pixelforgeGenerationFixture($generation);
    Http::fake(function (Request $request, array $options) use ($fixture) {
        expect($options['timeout'])->toBe(15);
        expect($options['connect_timeout'])->toBe(3);
        expect($options['allow_redirects'])->toBeFalse();

        return Http::response($fixture, 202, ['Idempotent-Replayed' => 'true']);
    });

    $snapshot = app(PixelforgeClient::class)->createGeneration($generation->request_payload, $generation->idempotency_key);

    expect($snapshot->id)->toBe($fixture['id']);
    expect($snapshot->status)->toBe(GenerationStatus::Queued);
    expect($snapshot->idempotentReplayed)->toBeTrue();
    expect($snapshot->rates->toArray())->toBe($fixture['rates']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://pixelforge.promptedit.dev/v1/generations'
        && $request->hasHeader('Authorization', 'Bearer test-only-key')
        && $request->hasHeader('Content-Type', 'application/json')
        && $request->hasHeader('Idempotency-Key', $generation->idempotency_key)
        && $request->data() === $generation->request_payload);
    Http::assertSentCount(1);
});

test('retrieves an authenticated complete generation snapshot', function () {
    config(['services.pixelforge.api_key' => 'test-only-key']);
    $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/Pixelforge/succeeded-generation.json'), true, flags: JSON_THROW_ON_ERROR);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations/'.$fixture['id'] => Http::response($fixture)]);

    $snapshot = app(PixelforgeClient::class)->generation($fixture['id']);

    expect($snapshot->usage->totalTokens)->toBe(10411);
    expect($snapshot->completedAt->toIso8601String())->toBe('2026-11-04T14:03:29+00:00');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer test-only-key'));
});

test('rejects malformed accepted provider data without claiming rejection', function (array $changes) {
    config(['services.pixelforge.api_key' => 'test-only-key']);
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded, $changes), 202)]);

    try {
        app(PixelforgeClient::class)->createGeneration($generation->request_payload, $generation->idempotency_key);
        test()->fail('Malformed provider data was accepted.');
    } catch (PixelforgeException $exception) {
        expect($exception->providerCode)->toBe('invalid_response');
        expect($exception->uncertain)->toBeTrue();
    }
})->with([
    'missing usage' => [['usage' => null]],
    'invalid usage total' => [['usage' => ['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 1, 'reasoning_tokens' => 0, 'total_tokens' => 99]]],
    'numeric pricing rate' => [['rates' => ['input_text_tokens' => 5, 'input_image_tokens' => '10', 'output_tokens' => '40', 'reasoning_tokens' => '12.50']]],
    'unsupported provider state' => [['status' => 'pending']],
    'missing image' => [['image_url' => null]],
    'invalid timestamp' => [['completed_at' => 'tomorrow']],
    'invalid calendar date' => [['completed_at' => '2026-02-30T12:00:00Z']],
]);

test('rejects unsupported public pricing and never substitutes hardcoded rates', function () {
    $fixture = pixelforgePricingFixture();
    $fixture['currency'] = 'eur';
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response($fixture)]);

    expect(fn () => app(PixelforgeClient::class)->pricing())->toThrow(PixelforgeException::class);
    Http::assertSentCount(1);
});

test('maps documented errors by code and retains Retry-After without exposing raw messages', function (int $status, string $code, bool $retryable) {
    config(['services.pixelforge.api_key' => 'test-only-key']);
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeErrorFixture($code), $status, ['Retry-After' => '120'])]);

    try {
        app(PixelforgeClient::class)->createGeneration($generation->request_payload, $generation->idempotency_key);
        test()->fail('Expected a provider exception.');
    } catch (PixelforgeException $exception) {
        expect($exception->providerCode)->toBe($code);
        expect($exception->retryable)->toBe($retryable);
        expect($exception->uncertain)->toBeFalse();
        expect($exception->retryAfterSeconds)->toBe(120);
        expect($exception->getMessage())->not->toContain('Sensitive provider');
        expect($exception->getPrevious())->toBeNull();
    }
})->with([
    [401, 'invalid_api_key', false],
    [422, 'validation_failed', false],
    [400, 'content_policy_violation', false],
    [409, 'idempotency_key_reused', false],
    [429, 'rate_limit_exceeded', true],
    [429, 'concurrency_limit_exceeded', true],
    [429, 'daily_limit_exceeded', false],
    [503, 'overloaded', true],
]);

test('treats a POST timeout as ambiguous and does not retry inside the client', function () {
    config(['services.pixelforge.api_key' => 'test-only-key']);
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::failedConnection()]);

    try {
        app(PixelforgeClient::class)->createGeneration($generation->request_payload, $generation->idempotency_key);
        test()->fail('Expected a connection failure.');
    } catch (PixelforgeException $exception) {
        expect($exception->providerCode)->toBe('connection_failure');
        expect($exception->retryable)->toBeTrue();
        expect($exception->uncertain)->toBeTrue();
    }
});

test('does not retain failed pricing in its cache', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::sequence()->push(pixelforgeErrorFixture('overloaded'), 503)->push(pixelforgePricingFixture())]);

    expect(fn () => app(PixelforgeClient::class)->pricing())->toThrow(PixelforgeException::class);
    expect(app(PixelforgeClient::class)->pricing()->rates->inputTextTokens)->toBe('5.00');
    Http::assertSentCount(2);
});

test('handles documented not-found errors during authenticated retrieval', function () {
    config(['services.pixelforge.api_key' => 'test-only-key']);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations/gen_missing' => Http::response(pixelforgeErrorFixture('not_found'), 404)]);

    expect(fn () => app(PixelforgeClient::class)->generation('gen_missing'))
        ->toThrow(PixelforgeException::class, 'not found');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-only-key'));
});
