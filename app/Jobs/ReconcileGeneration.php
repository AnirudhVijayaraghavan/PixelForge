<?php

namespace App\Jobs;

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Generations\Enums\GenerationStatus;
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
use UnexpectedValueException;

class ReconcileGeneration implements ShouldQueue
{
    use Queueable;

    public const int MAX_RECONCILIATIONS = 20;

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
        $generation = DB::transaction(function (): ?Generation {
            $generation = Generation::query()->lockForUpdate()->find($this->generationId);

            if ($generation === null || ! $this->canPoll($generation)
                || $generation->reconciliation_attempts >= self::MAX_RECONCILIATIONS
                || $generation->reconciliation_stopped_at !== null
                || $generation->next_reconciliation_attempt_at?->isFuture()
                || ($generation->last_reconciliation_attempt_at ?? $generation->provider_created_at ?? $generation->created_at)->greaterThan(now()->subSeconds(120))) {
                return null;
            }

            $generation->reconciliation_attempts++;
            $generation->last_reconciliation_attempt_at = now();
            $generation->next_reconciliation_attempt_at = now()->addSeconds(120);
            $generation->recovery_queued_until = now()->addSeconds(60);
            $generation->recovery_dispatches = 0;
            $generation->save();

            return $generation;
        });

        if ($generation === null) {
            return;
        }

        try {
            $snapshot = $client->generation($generation->provider_generation_id ?? throw new UnexpectedValueException('Missing provider identity.'));
            $applySnapshot->handle($generation->id, $snapshot);
            $this->finish();
        } catch (PixelforgeException $exception) {
            $this->finish($exception);
        } catch (UnexpectedValueException) {
            $this->finish(new PixelforgeException('snapshot_mismatch', 'The provider snapshot conflicts with the accepted generation.'));
        } catch (Throwable) {
            $this->finish(PixelforgeException::invalidResponse());
        }
    }

    private function canPoll(Generation $generation): bool
    {
        return $generation->provider_generation_id !== null
            && in_array($generation->provider_status, [GenerationStatus::Queued, GenerationStatus::Processing], true);
    }

    private function finish(?PixelforgeException $exception = null): void
    {
        $delay = DB::transaction(function () use ($exception): int|false|null {
            $generation = Generation::query()->lockForUpdate()->findOrFail($this->generationId);

            if (! $this->canPoll($generation)) {
                return false;
            }

            $exhausted = $generation->reconciliation_attempts >= self::MAX_RECONCILIATIONS;
            $stopped = $exhausted || ($exception !== null && ! $exception->retryable);
            $generation->reconciliation_error_code = $exception !== null ? $exception->providerCode : ($exhausted ? 'attempts_exhausted' : null);
            $generation->reconciliation_error_message = $exception !== null ? $exception->getMessage() : ($exhausted ? 'Automatic reconciliation is exhausted; operator review is required.' : null);
            $generation->reconciliation_stopped_at = $stopped ? now() : null;
            $generation->recovery_queued_until = null;
            $delay = null;

            if ($stopped) {
                $generation->next_reconciliation_attempt_at = null;
            } else {
                $backoff = [120, 120, 300, 600, 900];
                $seconds = $exception === null ? 120 : max($backoff[min(4, $generation->reconciliation_attempts - 1)], $exception->retryAfterSeconds ?? 0);
                $generation->next_reconciliation_attempt_at = now()->addSeconds($seconds);

                if ($exception !== null) {
                    $delay = $seconds;
                    $generation->recovery_queued_until = now()->addSeconds($seconds + 300);
                }
            }

            $generation->save();

            return $delay;
        });

        if ($delay === false) {
            return;
        }

        if ($exception !== null) {
            Log::warning('Pixelforge reconciliation did not complete.', ['generation_id' => $this->generationId, 'code' => $exception->providerCode]);

            if ($delay !== null) {
                $this->release($delay);
            } else {
                $this->fail($exception);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $generation = Generation::query()->lockForUpdate()->find($this->generationId);

            if ($generation === null || ! $this->canPoll($generation)) {
                return;
            }

            $generation->recovery_queued_until = null;
            $generation->reconciliation_error_code ??= 'worker_failed';
            $generation->reconciliation_error_message ??= 'The reconciliation worker stopped; provider state remains unresolved.';
            $generation->save();
        });
    }
}
