<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\PixelforgeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 5));
    config(['services.pixelforge.webhook_secret' => 'test-webhook-secret', 'services.pixelforge.api_key' => 'test-only-key']);
});

/** @param array<string, mixed> $event
 * @param  array<string, string|null>  $headers
 */
function deliverPixelforgeWebhook(array $event, array $headers = [], ?string $raw = null): TestResponse
{
    $raw ??= json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    return test()->call('POST', '/webhooks/pixelforge', [], [], [], array_replace([
        'CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => pixelforgeSignature($raw),
    ], $headers), $raw);
}

test('accepts a stateless signed ping and authenticates exact Unicode raw bytes', function () {
    $event = pixelforgeWebhookFixture(changes: ['data' => ['message' => '雪 🎨 café']]);
    $raw = " \n".json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
    deliverPixelforgeWebhook($event, raw: $raw)->assertNoContent();
    expect(PixelforgeWebhookEvent::query()->sole()->generation_id)->toBeNull();
    expect(PixelforgeWebhookEvent::query()->sole()->processed_at)->not->toBeNull();
    expect(json_encode(PixelforgeWebhookEvent::query()->sole()->diagnostic_metadata))->not->toContain('café', 'test-webhook-secret');
    Http::assertNothingSent();
});

test('rejects unauthenticated raw payloads before any database mutation', function (string $case) {
    $generation = Generation::factory()->create();
    $before = $generation->fresh()->getAttributes();
    $event = pixelforgeWebhookFixture($generation);
    $raw = json_encode($event, JSON_THROW_ON_ERROR);
    $signature = match ($case) {
        'missing' => null,
        'wrong secret' => pixelforgeSignature($raw, secret: 'incorrect'),
        'modified raw body' => pixelforgeSignature($raw.' '),
        'expired' => pixelforgeSignature($raw, now()->getTimestamp() - 301),
        'future' => pixelforgeSignature($raw, now()->getTimestamp() + 301),
        'malformed timestamp' => 't=1e9,v1='.str_repeat('a', 64),
        'duplicate timestamp' => pixelforgeSignature($raw).',t='.now()->getTimestamp(),
        'overflow timestamp' => 't=999999999999999999999,v1='.str_repeat('a', 64),
        'malformed digest' => 't='.now()->getTimestamp().',v1=invalid',
        default => pixelforgeSignature($raw),
    };
    if ($case === 'missing configuration') {
        config(['services.pixelforge.webhook_secret' => null]);
    }
    deliverPixelforgeWebhook($event, ['HTTP_PIXELFORGE_SIGNATURE' => $signature], $raw)->assertUnauthorized();
    expect($generation->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('pixelforge_webhook_events', 0);
    $this->assertDatabaseCount('generation_settlements', 0);
})->with(['missing', 'wrong secret', 'modified raw body', 'expired', 'future', 'malformed timestamp', 'duplicate timestamp', 'overflow timestamp', 'malformed digest', 'missing configuration']);

test('accepts exactly the signed timestamp tolerance boundaries', function (int $offset) {
    $event = pixelforgeWebhookFixture();
    $raw = json_encode($event, JSON_THROW_ON_ERROR);
    deliverPixelforgeWebhook($event, ['HTTP_PIXELFORGE_SIGNATURE' => pixelforgeSignature($raw, now()->getTimestamp() + $offset)], $raw)->assertNoContent();
})->with([-300, 300]);

test('rejects invalid authenticated envelopes and disagreeing convenience headers', function (string $case) {
    $generation = Generation::factory()->accepted()->create();
    $event = pixelforgeWebhookFixture($generation);
    $headers = [];
    match ($case) {
        'header id' => $headers['HTTP_PIXELFORGE_EVENT_ID'] = 'evt_other',
        'header type' => $headers['HTTP_PIXELFORGE_EVENT_TYPE'] = 'generation.failed',
        'event id' => $event['id'] = 'invalid',
        'object' => $event['object'] = 'generation',
        'type' => $event['type'] = 'generation.unknown',
        'timestamp' => $event['created_at'] = '2026-02-30T12:00:00Z',
        'status' => $event['type'] = 'generation.processing',
        'missing generation' => $event['data'] = [],
        'local identity' => $event['data']['object']['metadata']['local_generation_id'] = 'not-a-uuid',
        'provider identity' => $event['data']['object']['id'] = 'gen_other',
        'immutable input' => $event['data']['object']['prompt'] = 'Somebody else’s request',
        'rates' => $event['data']['object']['rates']['output_tokens'] = '999.00',
        'missing usage' => $event['data']['object']['usage'] = null,
        'negative usage' => $event['data']['object']['usage']['input_text_tokens'] = -1,
        'overflow usage' => $event['data']['object']['usage'] = ['input_text_tokens' => PHP_INT_MAX, 'input_image_tokens' => PHP_INT_MAX, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'total_tokens' => 0],
        'bad json' => null,
    };
    deliverPixelforgeWebhook($event, $headers, $case === 'bad json' ? '{' : null)->assertBadRequest();
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Queued);
    $this->assertDatabaseCount('pixelforge_webhook_events', 0);
    $this->assertDatabaseCount('generation_settlements', 0);
})->with(['header id', 'header type', 'event id', 'object', 'type', 'timestamp', 'status', 'missing generation', 'local identity', 'provider identity', 'immutable input', 'rates', 'missing usage', 'negative usage', 'overflow usage', 'bad json']);

test('requests redelivery when a valid local generation is temporarily missing', function () {
    $generation = Generation::factory()->make();
    deliverPixelforgeWebhook(pixelforgeWebhookFixture($generation))->assertServiceUnavailable()->assertDontSee($generation->id);
    $this->assertDatabaseCount('pixelforge_webhook_events', 0);
});

test('applies processing and records its authenticated association', function () {
    $generation = Generation::factory()->create();
    $event = pixelforgeWebhookFixture($generation, GenerationStatus::Processing);
    deliverPixelforgeWebhook($event, ['HTTP_PIXELFORGE_EVENT_ID' => $event['id'], 'HTTP_PIXELFORGE_EVENT_TYPE' => $event['type']])->assertNoContent();
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Processing);
    expect($generation->fresh()->provider_generation_id)->toBe($event['data']['object']['id']);
    expect(PixelforgeWebhookEvent::query()->sole()->generation_id)->toBe($generation->id);
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('settles official usage once across identical and distinct successful event IDs without duplicate writes', function () {
    $generation = Generation::factory()->create();
    $event = pixelforgeWebhookFixture($generation);
    $event['data']['object']['usage'] = ['input_text_tokens' => 115, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000, 'total_tokens' => 16095];
    deliverPixelforgeWebhook($event)->assertNoContent();
    $before = $generation->fresh()->getAttributes();
    $eventBefore = PixelforgeWebhookEvent::query()->sole()->getAttributes();
    $this->travel(5)->minutes();
    deliverPixelforgeWebhook($event)->assertNoContent();
    expect($generation->fresh()->getAttributes())->toBe($before);
    expect(PixelforgeWebhookEvent::query()->sole()->getAttributes())->toBe($eventBefore);
    $event['id'] = 'evt_anotherSuccess';
    deliverPixelforgeWebhook($event)->assertNoContent();
    $stored = $generation->fresh();
    expect($stored->settlement->provider_cost_nanodollars)->toBe('373875000');
    expect($stored->settlement->customer_charge_nanodollars)->toBe('467343750');
    expect($stored->settled_at->equalTo($stored->settlement->settled_at))->toBeTrue();
    expect($stored->image_url)->toBe($event['data']['object']['image_url']);
    expect($stored->image_url_expires_at->toIso8601String())->toBe('2026-11-18T12:00:02+00:00');
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('pixelforge_webhook_events', 2);
});

test('never regresses either terminal state and failures remain free', function (GenerationStatus $terminal) {
    $generation = Generation::factory()->create();
    deliverPixelforgeWebhook(pixelforgeWebhookFixture($generation, $terminal))->assertNoContent();
    $before = $generation->fresh()->getAttributes();
    $this->travel(1)->minutes();
    deliverPixelforgeWebhook(pixelforgeWebhookFixture($generation, GenerationStatus::Processing))->assertNoContent();
    expect($generation->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('pixelforge_webhook_events', 2);
    $this->assertDatabaseCount('generation_settlements', $terminal === GenerationStatus::Succeeded ? 1 : 0);
    if ($terminal === GenerationStatus::Failed) {
        expect($generation->fresh()->provider_error_code)->toBe('generation_failed');
        expect($generation->fresh()->settled_at)->toBeNull();
        expect($generation->fresh()->actual_token_usage)->toBeNull();
    }
})->with([GenerationStatus::Succeeded, GenerationStatus::Failed]);

test('handles a success webhook inside POST then a queued response and duplicate submission job', function () {
    $generation = Generation::factory()->create();
    $baseline = DB::transactionLevel();
    Http::fake(function () use ($generation, $baseline) {
        expect(DB::transactionLevel())->toBe($baseline);
        deliverPixelforgeWebhook(pixelforgeWebhookFixture($generation))->assertNoContent();

        return Http::response(pixelforgeGenerationFixture($generation), 202);
    });
    foreach ([1, 2] as $attempt) {
        (new SubmitGeneration($generation->id))->withFakeQueueInteractions()->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
    }
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertSentCount(1);
});

test('rolls back event and settlement on a transient insertion failure and permits safe redelivery', function () {
    Log::spy();
    $generation = Generation::factory()->create();
    $event = pixelforgeWebhookFixture($generation);
    $fail = true;
    $dispatcher = GenerationSettlement::getEventDispatcher();
    GenerationSettlement::setEventDispatcher(clone $dispatcher);
    GenerationSettlement::created(function () use (&$fail) {
        if ($fail) {
            throw new RuntimeException('Sensitive provider error details must not be logged.');
        }
    });
    try {
        deliverPixelforgeWebhook($event)->assertServiceUnavailable()->assertDontSee('Sensitive');
        expect($generation->fresh()->provider_status)->toBeNull();
        $this->assertDatabaseCount('generation_settlements', 0);
        $this->assertDatabaseCount('pixelforge_webhook_events', 0);
        $fail = false;
        deliverPixelforgeWebhook($event)->assertNoContent();
        $this->assertDatabaseCount('generation_settlements', 1);
        $this->assertDatabaseCount('pixelforge_webhook_events', 1);
    } finally {
        GenerationSettlement::setEventDispatcher($dispatcher);
    }
    Log::shouldHaveReceived('warning')->once()->with('Pixelforge webhook transaction did not complete.', ['event_id' => $event['id'], 'code' => 'processing_failed']);
});

test('refuses different confirmed usage after settlement', function () {
    $generation = Generation::factory()->create();
    $event = pixelforgeWebhookFixture($generation);
    deliverPixelforgeWebhook($event)->assertNoContent();
    $event['id'] = 'evt_changedUsage';
    $event['data']['object']['usage']['input_text_tokens']++;
    $event['data']['object']['usage']['total_tokens']++;
    deliverPixelforgeWebhook($event)->assertBadRequest();
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('pixelforge_webhook_events', 1);
});

test('refuses a reused event ID for a different generation without affecting either owner', function () {
    $first = Generation::factory()->create();
    $second = Generation::factory()->create();
    $event = pixelforgeWebhookFixture($first);
    deliverPixelforgeWebhook($event)->assertNoContent();
    $replacement = pixelforgeWebhookFixture($second, changes: ['id' => $event['id']]);
    deliverPixelforgeWebhook($replacement)->assertBadRequest();
    expect($second->fresh()->provider_generation_id)->toBeNull();
    expect($first->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('pixelforge_webhook_events', 1);
});

test('does not attach provider acceptance to a definitively rejected local request', function () {
    $generation = Generation::factory()->create(['submission_status' => SubmissionStatus::Rejected]);
    deliverPixelforgeWebhook(pixelforgeWebhookFixture($generation))->assertBadRequest();
    expect($generation->fresh()->provider_generation_id)->toBeNull();
    $this->assertDatabaseCount('pixelforge_webhook_events', 0);
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('repairs the settlement projection from confirmed data without replacing terminal metadata', function () {
    $generation = Generation::factory()->create();
    $snapshot = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded));
    app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);
    DB::table('generations')->where('id', $generation->id)->update(['settled_at' => null]);
    app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation)));
    expect($generation->fresh()->settled_at->equalTo($generation->fresh()->settlement->settled_at))->toBeTrue();
    expect($generation->fresh()->image_url)->toBe($snapshot->imageUrl);
    $this->assertDatabaseCount('generation_settlements', 1);
});
