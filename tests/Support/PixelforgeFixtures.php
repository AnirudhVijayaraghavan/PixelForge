<?php

declare(strict_types=1);

use App\Domain\Generations\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Str;

/** @return array<string, mixed> */
function pixelforgePricingFixture(): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/Pixelforge/pricing.json'), true, flags: JSON_THROW_ON_ERROR);
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function pixelforgeGenerationFixture(Generation $generation, GenerationStatus $status = GenerationStatus::Queued, array $changes = []): array
{
    $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/Pixelforge/succeeded-generation.json'), true, flags: JSON_THROW_ON_ERROR);
    $terminal = in_array($status, [GenerationStatus::Succeeded, GenerationStatus::Failed], true);

    return array_replace($fixture, [
        'id' => $generation->provider_generation_id ?? 'gen_'.str_replace('-', '', $generation->id),
        'status' => $status->value,
        'prompt' => $generation->prompt,
        'size' => $generation->size->value,
        'quality' => $generation->quality->value,
        'reference_images' => $generation->reference_images,
        'metadata' => $generation->request_payload['metadata'],
        'rates' => $generation->accepted_provider_rates ?? pixelforgePricingFixture()['rates'],
        'created_at' => '2026-11-17T12:00:00Z',
        'started_at' => $status === GenerationStatus::Queued ? null : '2026-11-17T12:00:01Z',
        'completed_at' => $terminal ? '2026-11-17T12:00:02Z' : null,
        'usage' => $status === GenerationStatus::Succeeded ? $generation->quote_snapshot['estimated_usage'] : null,
        'image_url' => $status === GenerationStatus::Succeeded ? $fixture['image_url'] : null,
        'image_url_expires_at' => $status === GenerationStatus::Succeeded ? '2026-11-18T12:00:02Z' : null,
        'error' => $status === GenerationStatus::Failed ? ['code' => 'generation_failed', 'message' => 'The provider could not produce an image.'] : null,
    ], $changes);
}

/** @return array{error: array{type: string, code: string, message: string, param: null, errors: array{}}} */
function pixelforgeErrorFixture(string $code): array
{
    $type = match ($code) {
        'invalid_api_key' => 'authentication_error',
        'idempotency_key_reused' => 'idempotency_error',
        'rate_limit_exceeded', 'concurrency_limit_exceeded', 'daily_limit_exceeded' => 'rate_limit_error',
        'overloaded' => 'api_error',
        default => 'invalid_request_error',
    };

    return ['error' => ['type' => $type, 'code' => $code, 'message' => 'Sensitive provider error details must not be logged.', 'param' => null, 'errors' => []]];
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function pixelforgeWebhookFixture(?Generation $generation = null, GenerationStatus $status = GenerationStatus::Succeeded, array $changes = []): array
{
    return array_replace([
        'id' => 'evt_'.Str::ulid(), 'object' => 'event',
        'type' => $generation === null ? 'ping' : 'generation.'.$status->value,
        'created_at' => '2026-11-17T12:00:02Z',
        'data' => $generation === null ? [] : ['object' => pixelforgeGenerationFixture($generation, $status)],
    ], $changes);
}

function pixelforgeSignature(string $raw, ?int $timestamp = null, string $secret = 'test-webhook-secret'): string
{
    $timestamp ??= now()->getTimestamp();

    return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$raw, $secret);
}
