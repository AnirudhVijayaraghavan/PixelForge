<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Actions\Generations\ProcessPixelforgeWebhook;
use App\Console\Commands\ReconcilePixelforgeGenerations;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeException;
use App\Integrations\Pixelforge\PixelforgeWebhookData;
use App\Jobs\ReconcileGeneration;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 5));
    config(['services.pixelforge.api_key' => 'test-only-key']);
});

function executeReconciliationJob(Generation $generation): ReconcileGeneration
{
    $job = (new ReconcileGeneration($generation->id))->withFakeQueueInteractions();
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    return $job;
}

test('recovers a missed success webhook with GET outside transactions and settles once', function () {
    $generation = Generation::factory()->accepted()->create(['provider_created_at' => now()->subMinutes(5)]);
    $baseline = DB::transactionLevel();
    Http::fake(function (Request $request) use ($generation, $baseline) {
        expect(DB::transactionLevel())->toBe($baseline);
        expect($request->method())->toBe('GET');
        expect($request->url())->toEndWith('/generations/'.$generation->provider_generation_id);

        return Http::response(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded));
    });
    executeReconciliationJob($generation)->assertNotFailed();
    executeReconciliationJob($generation);
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    expect($generation->fresh()->reconciliation_attempts)->toBe(1);
    expect($generation->fresh()->next_reconciliation_attempt_at)->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertSentCount(1);
});

test('keeps a slow provider generation processing and spaces successful polls', function () {
    $generation = Generation::factory()->accepted()->create(['provider_created_at' => now()->subSeconds(90)]);
    Http::fake(['*' => Http::response(pixelforgeGenerationFixture($generation, GenerationStatus::Processing))]);
    executeReconciliationJob($generation);
    Http::assertNothingSent();
    $this->travel(30)->seconds();
    executeReconciliationJob($generation);
    executeReconciliationJob($generation);
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Processing);
    expect($generation->fresh()->next_reconciliation_attempt_at->equalTo(now()->addSeconds(120)))->toBeTrue();
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertSentCount(1);
});

test('persists reconciliation Retry-After and safe errors without fabricating a provider failure', function (int $status, string $code, bool $retry) {
    $generation = Generation::factory()->accepted()->create(['provider_created_at' => now()->subMinutes(5)]);
    Http::fake(['*' => Http::response(pixelforgeErrorFixture($code), $status, ['Retry-After' => '420'])]);
    $job = executeReconciliationJob($generation);
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Queued);
    expect($generation->fresh()->reconciliation_error_code)->toBe($code);
    expect($generation->fresh()->reconciliation_error_message)->not->toContain('Sensitive provider');
    if ($retry) {
        $job->assertReleased(420)->assertNotFailed();
        expect($generation->fresh()->next_reconciliation_attempt_at->equalTo(now()->addSeconds(420)))->toBeTrue();
        executeReconciliationJob($generation);
    } else {
        $job->assertFailedWith(PixelforgeException::class)->assertNotReleased();
        expect($generation->fresh()->reconciliation_stopped_at)->not->toBeNull();
        $this->travel(1)->days();
        executeReconciliationJob($generation);
    }
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertSentCount(1);
})->with([[429, 'rate_limit_exceeded', true], [503, 'overloaded', true], [401, 'invalid_api_key', false], [404, 'not_found', false]]);

test('bounds malformed polling responses and preserves an explicit unresolved state after exhaustion', function () {
    Queue::fake();
    $generation = Generation::factory()->accepted()->create([
        'provider_created_at' => now()->subHours(2), 'reconciliation_attempts' => ReconcileGeneration::MAX_RECONCILIATIONS - 1,
    ]);
    Http::fake(['*' => Http::response(['object' => 'generation'])]);
    executeReconciliationJob($generation)->assertFailed();
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Queued);
    expect($generation->fresh()->reconciliation_attempts)->toBe(20);
    expect($generation->fresh()->reconciliation_stopped_at)->not->toBeNull();
    $this->travel(1)->days();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    executeReconciliationJob($generation);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
});

test('handles a webhook while reconciliation GET is in flight without duplicate settlement', function () {
    $generation = Generation::factory()->accepted()->create(['provider_created_at' => now()->subMinutes(5)]);
    Http::fake(function () use ($generation) {
        app(ProcessPixelforgeWebhook::class)->handle(PixelforgeWebhookData::fromArray(pixelforgeWebhookFixture($generation)));

        return Http::response(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded));
    });
    executeReconciliationJob($generation);
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('pixelforge_webhook_events', 1);
    Http::assertSentCount(1);
});

test('discards a reconciliation error superseded by a successful webhook', function () {
    $generation = Generation::factory()->accepted()->create(['provider_created_at' => now()->subMinutes(5)]);
    Http::fake(function () use ($generation) {
        app(ProcessPixelforgeWebhook::class)->handle(PixelforgeWebhookData::fromArray(pixelforgeWebhookFixture($generation)));

        return Http::response(pixelforgeErrorFixture('overloaded'), 503);
    });
    executeReconciliationJob($generation)->assertNotFailed()->assertNotReleased();
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    expect($generation->fresh()->reconciliation_error_code)->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertSentCount(1);
});

test('selects bounded stale work once and excludes healthy leased terminal and rejected records', function () {
    Queue::fake();
    $stale = Generation::factory()->accepted()->create(['provider_created_at' => now()->subMinutes(5)]);
    $stranded = Generation::factory()->create(['created_at' => now()->subMinutes(6)]);
    Generation::factory()->accepted()->create();
    Generation::factory()->accepted()->create(['provider_created_at' => now()->subMinutes(5), 'next_reconciliation_attempt_at' => now()->addMinutes(2)]);
    Generation::factory()->accepted()->create(['provider_created_at' => now()->subMinutes(5), 'recovery_queued_until' => now()->addMinutes(5)]);
    Generation::factory()->failed()->create(['provider_created_at' => now()->subMinutes(5)]);
    Generation::factory()->succeeded()->create(['provider_created_at' => now()->subMinutes(5)]);
    Generation::factory()->create(['created_at' => now()->subMinutes(5), 'submission_status' => SubmissionStatus::Rejected, 'submission_error_code' => 'content_policy_violation']);
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertPushed(ReconcileGeneration::class, fn (ReconcileGeneration $job) => $job->generationId === $stale->id);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generationId === $stranded->id);
    Queue::assertPushed(ReconcileGeneration::class, 1);
    Queue::assertPushed(SubmitGeneration::class, 1);
    expect($stranded->fresh()->recovery_dispatches)->toBe(1);
    expect($stranded->fresh()->recovery_queued_until->equalTo(now()->addMinutes(5)))->toBeTrue();
    Http::assertNothingSent();
});

test('repairs expired submitting and ambiguous worker failures using the original immutable POST identity', function (SubmissionStatus $state) {
    Queue::fake();
    $generation = Generation::factory()->create([
        'created_at' => now()->subMinutes(5), 'submission_status' => $state, 'submission_attempts' => 1,
        'last_submission_attempt_at' => now()->subMinutes(2), 'submission_error_code' => 'worker_failed',
    ]);
    $payload = $generation->request_payload;
    $key = $generation->idempotency_key;
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertPushed(SubmitGeneration::class, 1);
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Uncertain);
    Http::fake(function (Request $request) use ($generation, $payload, $key) {
        expect($request->data())->toBe($payload);
        expect($request->header('Idempotency-Key'))->toBe([$key]);

        return Http::response(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded), 202, ['Idempotent-Replayed' => 'true']);
    });
    (new SubmitGeneration($generation->id))->withFakeQueueInteractions()->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Succeeded);
    expect($generation->fresh()->submission_attempts)->toBe(2);
    expect($generation->fresh()->idempotency_key)->toBe($key);
    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertSentCount(1);
})->with([SubmissionStatus::Submitting, SubmissionStatus::Uncertain]);

test('honors retry deadlines permanent uncertainty and persisted provider and dispatch budgets', function () {
    Queue::fake();
    $old = ['created_at' => now()->subMinutes(10)];
    Generation::factory()->create($old + ['submission_status' => SubmissionStatus::Uncertain, 'submission_error_code' => 'invalid_api_key']);
    Generation::factory()->create($old + ['submission_status' => SubmissionStatus::Uncertain, 'submission_error_code' => 'connection_failure', 'submission_attempts' => 5]);
    Generation::factory()->create($old + ['next_submission_attempt_at' => now()->addMinutes(10)]);
    Generation::factory()->create($old + ['submission_status' => SubmissionStatus::Submitting, 'last_submission_attempt_at' => now()->subSeconds(20)]);
    Generation::factory()->create($old + ['recovery_dispatches' => ReconcilePixelforgeGenerations::MAX_RECOVERY_DISPATCHES]);
    Generation::factory()->accepted()->create(['provider_created_at' => now()->subHours(2), 'reconciliation_attempts' => 20]);
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('reopens a quota rejection only in the next UTC quota period without resetting the attempt budget', function () {
    Queue::fake();
    $generation = Generation::factory()->create([
        'created_at' => now()->subMinutes(10), 'submission_status' => SubmissionStatus::Rejected,
        'submission_error_code' => 'daily_limit_exceeded', 'submission_attempts' => 1,
        'next_submission_attempt_at' => now()->utc()->addDay()->startOfDay(),
    ]);
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertNothingPushed();
    $this->travel(1)->days();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertPushed(SubmitGeneration::class, 1);
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Pending);
    expect($generation->fresh()->submission_attempts)->toBe(1);
});

test('honors a daily-quota Retry-After longer than the next UTC midnight', function () {
    Queue::fake();
    $generation = Generation::factory()->create(['created_at' => now()->subMinutes(10)]);
    Http::fake(['*' => Http::response(pixelforgeErrorFixture('daily_limit_exceeded'), 429, ['Retry-After' => '86400'])]);
    (new SubmitGeneration($generation->id))->withFakeQueueInteractions()->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
    expect($generation->fresh()->next_submission_attempt_at->equalTo(now()->addDay()))->toBeTrue();
    $this->travel(12)->hours();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertNothingPushed();
    $this->travel(12)->hours();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertPushed(SubmitGeneration::class, 1);
    Http::assertSentCount(1);
});

test('limits each repair batch and validates operator limits', function () {
    Queue::fake();
    Generation::factory()->count(3)->create(['created_at' => now()->subMinutes(10)]);
    $this->artisan('pixelforge:reconcile', ['--limit' => 2])->assertSuccessful();
    Queue::assertPushed(SubmitGeneration::class, 2);
    $this->artisan('pixelforge:reconcile', ['--limit' => 51])->assertExitCode(2);
});

test('repairs a lost recovery dispatch after its lease expires but never beyond the dispatch budget', function () {
    Queue::fake();
    $generation = Generation::factory()->create(['created_at' => now()->subMinutes(10), 'recovery_dispatches' => 8]);
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    $this->travel(5)->minutes();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    $this->travel(5)->minutes();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Queue::assertPushed(SubmitGeneration::class, 2);
    expect($generation->fresh()->recovery_dispatches)->toBe(10);
    expect($generation->fresh()->submission_attempts)->toBe(0);
});

test('supports a configured asynchronous connection and rejects synchronous or unsafe queue timing', function () {
    config(['services.pixelforge.queue_connection' => 'processing', 'queue.connections.processing' => ['driver' => 'database', 'retry_after' => 90]]);
    expect((new SubmitGeneration('local-id'))->connection)->toBe('processing');
    expect((new ReconcileGeneration('local-id'))->connection)->toBe('processing');
    config(['services.pixelforge.queue_connection' => 'sync']);
    expect(fn () => new SubmitGeneration('local-id'))->toThrow(LogicException::class, 'asynchronous');
    config(['services.pixelforge.queue_connection' => 'processing', 'queue.connections.processing.retry_after' => 30]);
    expect(fn () => new ReconcileGeneration('local-id'))->toThrow(LogicException::class, 'retry_after');
});

test('shares one account queue limiter between POST and reconciliation jobs', function () {
    $executed = 0;
    for ($index = 0; $index < 50; $index++) {
        $job = (new SubmitGeneration('local-id'))->withFakeQueueInteractions();
        $job->middleware()[0]->handle($job, function () use (&$executed) {
            $executed++;
        });
    }
    $poll = (new ReconcileGeneration('local-id'))->withFakeQueueInteractions();
    $poll->middleware()[0]->handle($poll, function () use (&$executed) {
        $executed++;
    });
    $poll->assertReleased(60);
    expect($executed)->toBe(50);
    Http::assertNothingSent();
});

test('parks workers that crashed on their final allowed attempt instead of leaving submitting forever', function () {
    Queue::fake();
    $submission = Generation::factory()->create([
        'created_at' => now()->subHours(2), 'submission_status' => SubmissionStatus::Submitting,
        'submission_attempts' => 5, 'last_submission_attempt_at' => now()->subMinutes(5),
    ]);
    $poll = Generation::factory()->accepted()->create([
        'provider_created_at' => now()->subHours(2), 'last_reconciliation_attempt_at' => now()->subMinutes(5), 'reconciliation_attempts' => 20,
    ]);
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    expect($submission->fresh()->submission_status)->toBe(SubmissionStatus::Uncertain);
    expect($submission->fresh()->submission_error_code)->toBe('attempts_exhausted');
    expect($poll->fresh()->reconciliation_stopped_at)->not->toBeNull();
    expect($poll->fresh()->provider_status)->toBe(GenerationStatus::Queued);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('prevents a concurrent duplicate uncertain claim from consuming another POST attempt', function (SubmissionStatus $state) {
    $generation = Generation::factory()->create([
        'submission_status' => $state, 'submission_attempts' => 1, 'last_submission_attempt_at' => now()->subMinutes(5),
    ]);
    Http::fake(function () use ($generation) {
        (new SubmitGeneration($generation->id))->withFakeQueueInteractions()->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

        return Http::response(pixelforgeGenerationFixture($generation), 202);
    });
    (new SubmitGeneration($generation->id))->withFakeQueueInteractions()->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
    expect($generation->fresh()->submission_attempts)->toBe(2);
    expect($generation->fresh()->submission_status)->toBe(SubmissionStatus::Submitted);
    Http::assertSentCount(1);
})->with([SubmissionStatus::Uncertain, SubmissionStatus::Submitting]);

test('durably queues work on the isolated database connection without running a provider request inline', function () {
    $generation = Generation::factory()->create();
    SubmitGeneration::dispatch($generation->id);
    $this->assertDatabaseHas('jobs', ['queue' => 'pixelforge']);
    $payload = DB::table('jobs')->sole()->payload;
    expect($payload)->toContain($generation->id);
    expect($payload)->not->toContain('test-only-key', $generation->prompt);
    Http::assertNothingSent();
});

test('continues a repair batch after post-commit queue insertion fails and later repairs the lost dispatch', function () {
    $generations = Generation::factory()->count(2)->create(['created_at' => now()->subMinutes(10)]);
    $baseline = DB::transactionLevel();
    $fail = true;
    DB::connection()->beforeExecuting(function (string $query) use (&$fail, $baseline): void {
        if (str_starts_with($query, 'insert into "jobs"')) {
            expect(DB::transactionLevel())->toBe($baseline);

            if ($fail) {
                $fail = false;
                throw new RuntimeException('Simulated queue unavailability after generation commit.');
            }
        }
    });

    $this->artisan('pixelforge:reconcile')->assertFailed();
    $this->assertDatabaseCount('jobs', 1);
    $payload = DB::table('jobs')->sole()->payload;
    $lost = $generations->first(fn (Generation $generation) => ! str_contains($payload, $generation->id));
    expect($lost->fresh()->recovery_dispatches)->toBe(1);
    expect($lost->fresh()->recovery_queued_until->equalTo(now()->addMinutes(5)))->toBeTrue();
    $this->travel(5)->minutes();
    $this->artisan('pixelforge:reconcile', ['--generation' => $lost->id])->assertSuccessful();
    $this->assertDatabaseCount('jobs', 2);
    expect($lost->fresh()->recovery_dispatches)->toBe(2);
    Http::assertNothingSent();
});
