<?php

namespace App\Jobs;

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeException;
use App\Integrations\Pixelforge\PixelforgeQueue;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public const int MAX_SUBMISSIONS = 5;

    public int $tries = 10;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(public string $generationId)
    {
        $this->onConnection(PixelforgeQueue::connection());
        $this->onQueue('pixelforge');
        $this->afterCommit();
    }

    /** @return list<RateLimited> */
    public function middleware(): array
    {
        return [(new RateLimited('pixelforge'))->releaseAfter(60)];
    }

    public function handle(PixelforgeClient $client, ApplyGenerationSnapshot $applySnapshot): void
    {
        $claim = $this->claim();

        if (is_int($claim)) {
            $this->release($claim);

            return;
        }

        if ($claim === null) {
            return;
        }

        try {
            $snapshot = $client->createGeneration($claim->request_payload, $claim->idempotency_key);
            $applySnapshot->handle($claim->id, $snapshot);
        } catch (PixelforgeException $exception) {
            $this->handleFailure($exception);
        } catch (Throwable) {
            /** Provider data or settlement validation may fail after POST was accepted. */
            $this->handleFailure(PixelforgeException::invalidResponse());
        }
    }

    private function claim(): Generation|int|null
    {
        return DB::transaction(function (): Generation|int|null {
            $generation = Generation::query()->lockForUpdate()->find($this->generationId);

            if ($generation === null || $generation->provider_generation_id !== null
                || in_array($generation->submission_status, [SubmissionStatus::Submitted, SubmissionStatus::Rejected], true)) {
                return null;
            }

            if ($generation->submission_attempts >= self::MAX_SUBMISSIONS) {
                if ($generation->submission_status === SubmissionStatus::Submitting) {
                    $generation->submission_status = SubmissionStatus::Uncertain;
                    $generation->submission_error_code = 'attempts_exhausted';
                    $generation->submission_error_message = 'Submission attempts are exhausted; recovery is required.';
                    $generation->save();
                }

                return null;
            }

            if ($generation->submission_status === SubmissionStatus::Uncertain
                && $generation->submission_error_code !== null && $generation->next_submission_attempt_at === null) {
                return null;
            }

            $availableAt = $generation->next_submission_attempt_at?->getTimestamp() ?? 0;

            if ($generation->submission_status === SubmissionStatus::Submitting && $generation->last_submission_attempt_at !== null) {
                $availableAt = max($availableAt, $generation->last_submission_attempt_at->getTimestamp() + 60);
            }

            $delay = $availableAt - now()->getTimestamp();

            if ($delay > 0) {
                return $delay;
            }

            /** An expired in-flight claim may have reached the provider before its worker stopped. */
            if ($generation->submission_status === SubmissionStatus::Submitting) {
                $generation->submission_status = SubmissionStatus::Uncertain;
            }

            if ($generation->submission_status !== SubmissionStatus::Uncertain) {
                $generation->submission_status = SubmissionStatus::Submitting;
            } else {
                $generation->submission_error_code ??= 'outcome_unknown';
                $generation->submission_error_message ??= 'A previous submission may have been accepted; its outcome remains unknown.';
            }

            $generation->submission_attempts++;
            $generation->last_submission_attempt_at = now();
            $generation->next_submission_attempt_at = null;
            $generation->recovery_dispatches = 0;
            $generation->recovery_queued_until = now()->addSeconds(60);
            $generation->save();

            return $generation;
        });
    }

    private function handleFailure(PixelforgeException $exception): void
    {
        $delay = DB::transaction(function () use ($exception): int|false|null {
            $generation = Generation::query()->lockForUpdate()->findOrFail($this->generationId);

            if ($generation->provider_generation_id !== null || $generation->submission_status === SubmissionStatus::Submitted) {
                return false;
            }

            $uncertain = $exception->uncertain || $generation->submission_status === SubmissionStatus::Uncertain;
            $retry = $exception->retryable && $generation->submission_attempts < self::MAX_SUBMISSIONS;
            $generation->submission_status = $uncertain
                ? SubmissionStatus::Uncertain
                : ($retry ? SubmissionStatus::Pending : SubmissionStatus::Rejected);
            $generation->submission_error_code = $exception->providerCode;
            $generation->submission_error_message = $exception->getMessage();
            $generation->next_submission_attempt_at = null;
            $generation->recovery_queued_until = null;
            $delay = null;

            if ($retry) {
                $backoff = [5, 15, 60, 180, 300];
                $delay = max($backoff[min(4, max(0, $generation->submission_attempts - 1))], $exception->retryAfterSeconds ?? 0);
                $generation->next_submission_attempt_at = now()->addSeconds($delay);
                $generation->recovery_queued_until = now()->addSeconds($delay + 300);
            } elseif ($exception->providerCode === 'daily_limit_exceeded') {
                $generation->next_submission_attempt_at = now()->utc()->addDay()->startOfDay()
                    ->max(now()->addSeconds($exception->retryAfterSeconds ?? 0));
            }

            $generation->save();

            return $delay;
        });

        if ($delay === false) {
            Log::notice('Pixelforge submission response was superseded by provider acceptance.', [
                'generation_id' => $this->generationId,
                'code' => $exception->providerCode,
            ]);

            return;
        }

        Log::warning('Pixelforge submission did not complete.', [
            'generation_id' => $this->generationId,
            'code' => $exception->providerCode,
            'retry_scheduled' => $delay !== null,
        ]);

        if ($delay !== null) {
            $this->release($delay);
        } else {
            $this->fail($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $generation = Generation::query()->lockForUpdate()->find($this->generationId);

            if ($generation === null || $generation->provider_generation_id !== null
                || in_array($generation->submission_status, [SubmissionStatus::Submitted, SubmissionStatus::Rejected], true)) {
                return;
            }

            if ($generation->submission_status === SubmissionStatus::Submitting) {
                $generation->submission_status = SubmissionStatus::Uncertain;
            }

            $generation->submission_error_code ??= 'worker_failed';
            $generation->submission_error_message ??= 'The submission worker stopped; this generation remains available for recovery.';
            $generation->recovery_queued_until = null;
            $generation->save();
        });
    }
}
