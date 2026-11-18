<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeException;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

beforeEach(function () {
    config(['services.pixelforge.api_key' => 'test-only-key']);
});

function executeSubmissionJob(Generation $generation): SubmitGeneration
{
    $job = (new SubmitGeneration($generation->id))->withFakeQueueInteractions();
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    return $job;
}

test('submits outside application transactions and applies every documented accepted lifecycle state', function (GenerationStatus $status) {
    $generation = Generation::factory()->create();
    $transactionLevel = DB::transactionLevel();
    Http::fake(function (Request $request) use ($generation, $status, $transactionLevel) {
        expect(DB::transactionLevel())->toBe($transactionLevel);
        expect(Generation::query()->findOrFail($generation->id)->submission_attempts)->toBe(1);

        return Http::response(pixelforgeGenerationFixture($generation, $status), 202, ['Idempotent-Replayed' => 'true']);
    });

    $job = executeSubmissionJob($generation);

    $job->assertNotFailed()->assertNotReleased();
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Submitted);
    expect($generation->fresh()->provider_status)->toBe($status);
    expect($generation->fresh()->settlement !== null)->toBe($status === GenerationStatus::Succeeded);
    Http::assertSentCount(1);
})->with([GenerationStatus::Queued, GenerationStatus::Processing, GenerationStatus::Succeeded, GenerationStatus::Failed]);

test('records documented submission rejections and delays transient errors without sleeping', function (int $httpStatus, string $code, bool $retry) {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeErrorFixture($code), $httpStatus, ['Retry-After' => '120'])]);

    $job = executeSubmissionJob($generation);
    $stored = $generation->fresh();

    expect($stored->provider_generation_id)->toBeNull();
    expect($stored->provider_status)->toBeNull();
    expect($stored->submission_error_code)->toBe($code);
    expect($stored->submission_error_message)->not->toContain('Sensitive provider');
    expect($stored->submission_attempts)->toBe(1);
    $this->assertDatabaseCount('generation_settlements', 0);

    if ($retry) {
        $job->assertReleased(120)->assertNotFailed();
        expect($stored->submission_status)->toBe(SubmissionStatus::Pending);
        expect($stored->next_submission_attempt_at->getTimestamp())->toBe(now()->addSeconds(120)->getTimestamp());
    } else {
        $job->assertFailedWith(PixelforgeException::class)->assertNotReleased();
        expect($stored->submission_status)->toBe(SubmissionStatus::Rejected);
    }
})->with([
    [400, 'content_policy_violation', false],
    [422, 'validation_failed', false],
    [401, 'invalid_api_key', false],
    [409, 'idempotency_key_reused', false],
    [429, 'rate_limit_exceeded', true],
    [429, 'concurrency_limit_exceeded', true],
    [503, 'overloaded', true],
]);

test('stops on the daily quota and records the next UTC quota period', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeErrorFixture('daily_limit_exceeded'), 429)]);

    $job = executeSubmissionJob($generation);
    executeSubmissionJob($generation);

    $job->assertFailedWith(PixelforgeException::class)->assertNotReleased();
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Rejected);
    expect($generation->fresh()->submission_error_code)->toBe('daily_limit_exceeded');
    expect($generation->fresh()->next_submission_attempt_at->equalTo(now()->utc()->addDay()->startOfDay()))->toBeTrue();
    Http::assertSentCount(1);
});

test('retries an ambiguous timeout with exactly the same payload and idempotency key', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    $requests = [];
    Http::fake(function (Request $request) use ($generation, &$requests) {
        $requests[] = ['body' => $request->body(), 'key' => $request->header('Idempotency-Key')];

        if (count($requests) === 1) {
            throw new ConnectionException('The response was lost after acceptance.');
        }

        return Http::response(pixelforgeGenerationFixture($generation), 202, ['Idempotent-Replayed' => 'true']);
    });

    $first = executeSubmissionJob($generation);
    $first->assertReleased(5);
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Uncertain);
    $this->travel(5)->seconds();
    $second = executeSubmissionJob($generation);

    $second->assertNotFailed();
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Submitted);
    expect($generation->fresh()->submission_attempts)->toBe(2);
    expect($requests)->toHaveCount(2);
    expect($requests[1])->toBe($requests[0]);
    expect($generation->fresh()->request_payload)->toBe($generation->request_payload);
    expect($generation->fresh()->idempotency_key)->toBe($generation->idempotency_key);
});

test('preserves uncertainty when a later definitive error cannot disprove an earlier acceptance', function () {
    $generation = Generation::factory()->create(['submission_status' => SubmissionStatus::Uncertain]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeErrorFixture('invalid_api_key'), 401)]);

    $job = executeSubmissionJob($generation);

    $job->assertFailedWith(PixelforgeException::class);
    executeSubmissionJob($generation);
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Uncertain);
    expect($generation->fresh()->provider_status)->toBeNull();
    Http::assertSentCount(1);
});

test('bounds ambiguous attempts and retains the original recoverable generation after exhaustion', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    $count = 0;
    Http::fake(function () use (&$count) {
        $count++;

        throw new ConnectionException('Timed out.');
    });

    foreach ([5, 15, 60, 180, null] as $delay) {
        $job = executeSubmissionJob($generation);

        if ($delay !== null) {
            $job->assertReleased($delay);
            $this->travel($delay)->seconds();
        }
    }

    $job->assertFailedWith(PixelforgeException::class);
    executeSubmissionJob($generation);
    expect($count)->toBe(5);
    expect($generation->fresh()->submission_attempts)->toBe(5);
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Uncertain);
    expect($generation->fresh()->idempotency_key)->toBe($generation->idempotency_key);
    expect($generation->fresh()->provider_generation_id)->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('defers a concurrent duplicate and skips it once the first worker settles', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    $duplicate = null;
    Http::fake(function () use ($generation, &$duplicate) {
        $duplicate = executeSubmissionJob($generation);

        return Http::response(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded), 202);
    });

    executeSubmissionJob($generation);
    $duplicate->assertReleased(60);
    executeSubmissionJob($generation)->assertNotReleased()->assertNotFailed();

    expect($generation->fresh()->submission_attempts)->toBe(1);
    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertSentCount(1);
});

test('keeps a terminal snapshot applied before the queued create response arrives', function () {
    $generation = Generation::factory()->create();
    Http::fake(function () use ($generation) {
        app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded)));

        return Http::response(pixelforgeGenerationFixture($generation), 202);
    });

    executeSubmissionJob($generation)->assertNotFailed();

    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    expect($generation->fresh()->settlement)->not->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 1);
});

test('does not overwrite a settled snapshot when the create request later reports an error', function () {
    $generation = Generation::factory()->create();
    Log::spy();
    Http::fake(function () use ($generation) {
        app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded)));

        return Http::response(pixelforgeErrorFixture('overloaded'), 503);
    });

    executeSubmissionJob($generation)->assertNotFailed()->assertNotReleased();

    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    expect($generation->fresh()->submission_error_code)->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 1);
    Log::shouldHaveReceived('notice')->once()->with('Pixelforge submission response was superseded by provider acceptance.', [
        'generation_id' => $generation->id, 'code' => 'overloaded',
    ]);
});

test('keeps malformed accepted data recoverable and logs only sanitized diagnostics', function () {
    $generation = Generation::factory()->create();
    Log::spy();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(
        pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded, ['usage' => null]), 202,
    )]);

    executeSubmissionJob($generation)->assertReleased(5);

    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Uncertain);
    expect($generation->fresh()->submission_error_code)->toBe('invalid_response');
    expect($generation->fresh()->provider_generation_id)->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 0);
    Log::shouldHaveReceived('warning')->once()->with('Pixelforge submission did not complete.', [
        'generation_id' => $generation->id, 'code' => 'invalid_response', 'retry_scheduled' => true,
    ]);
});

test('preserves an interrupted in-flight request for recovery', function () {
    $generation = Generation::factory()->create([
        'submission_status' => SubmissionStatus::Submitting, 'submission_attempts' => 1, 'last_submission_attempt_at' => now(),
    ]);

    (new SubmitGeneration($generation->id))->failed(new RuntimeException('Sensitive worker failure details.'));

    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Uncertain);
    expect($generation->fresh()->submission_error_code)->toBe('worker_failed');
    expect($generation->fresh()->submission_error_message)->not->toContain('Sensitive');
    expect($generation->fresh()->provider_status)->toBeNull();
});

test('honors a persisted retry deadline without calling the provider early', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create(['next_submission_attempt_at' => now()->addSeconds(120)]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeGenerationFixture($generation), 202)]);

    executeSubmissionJob($generation)->assertReleased(120);

    expect($generation->fresh()->submission_attempts)->toBe(0);
    Http::assertNothingSent();
});
