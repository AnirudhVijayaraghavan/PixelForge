<?php

namespace App\Console\Commands;

use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Jobs\ReconcileGeneration;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('pixelforge:reconcile {--limit=20 : Maximum jobs to dispatch, from 1 to 50} {--generation= : Restrict diagnosis to one local UUID without resetting budgets}')]
#[Description('Queue bounded recovery for stale Pixelforge generations and stranded submissions')]
class ReconcilePixelforgeGenerations extends Command
{
    public const int MAX_RECOVERY_DISPATCHES = 10;

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);

        if ($limit === false) {
            $this->error('The limit must be an integer between 1 and 50.');

            return self::INVALID;
        }

        $query = $this->eligible();

        if ($this->option('generation') !== null) {
            $query->whereKey($this->option('generation'));
        }

        $ids = $query->orderBy('created_at')->orderBy('id')->limit($limit)->pluck('id');
        $scheduled = 0;
        $errors = 0;

        foreach ($ids as $id) {
            try {
                $scheduled += DB::transaction(function () use ($id): int {
                    /** Recheck the durable dispatch lease under the row lock. */
                    $generation = $this->eligible()->whereKey($id)->lockForUpdate()->first();

                    if ($generation === null) {
                        return 0;
                    }

                    if ($generation->provider_generation_id !== null && $generation->reconciliation_attempts >= ReconcileGeneration::MAX_RECONCILIATIONS) {
                        $generation->reconciliation_stopped_at = now();
                        $generation->reconciliation_error_code = 'attempts_exhausted';
                        $generation->reconciliation_error_message = 'Automatic reconciliation is exhausted; operator review is required.';
                        $generation->next_reconciliation_attempt_at = null;
                        $generation->recovery_queued_until = null;
                        $generation->save();

                        return 0;
                    }

                    if ($generation->provider_generation_id === null && $generation->submission_attempts >= SubmitGeneration::MAX_SUBMISSIONS) {
                        $generation->submission_status = SubmissionStatus::Uncertain;
                        $generation->submission_error_code = 'attempts_exhausted';
                        $generation->submission_error_message = 'Submission attempts are exhausted; operator review is required.';
                        $generation->next_submission_attempt_at = null;
                        $generation->recovery_queued_until = null;
                        $generation->save();

                        return 0;
                    }

                    if ($generation->provider_generation_id === null) {
                        if ($generation->submission_status === SubmissionStatus::Submitting) {
                            $generation->submission_status = SubmissionStatus::Uncertain;
                        } elseif ($generation->submission_status === SubmissionStatus::Rejected) {
                            /** Only an expired daily quota rejection is eligible here. */
                            $generation->submission_status = SubmissionStatus::Pending;
                        }

                        $generation->next_submission_attempt_at = now();
                    }

                    $generation->recovery_queued_until = now()->addMinutes(5);
                    $generation->recovery_dispatches++;
                    $generation->save();

                    if ($generation->provider_generation_id === null) {
                        SubmitGeneration::dispatch($generation->id)->afterCommit();
                    } else {
                        ReconcileGeneration::dispatch($generation->id)->afterCommit();
                    }

                    return 1;
                }, 3);
            } catch (Throwable) {
                $errors++;
                Log::warning('Pixelforge recovery dispatch did not complete.', ['generation_id' => $id, 'code' => 'dispatch_failed']);
            }
        }

        $this->info("Scheduled {$scheduled} recovery jobs; {$errors} dispatch errors. Budgets and leases were preserved.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Builder<Generation> */
    private function eligible(): Builder
    {
        return Generation::query()
            ->where('recovery_dispatches', '<', self::MAX_RECOVERY_DISPATCHES)
            ->where(fn (Builder $query) => $query->whereNull('recovery_queued_until')->orWhere('recovery_queued_until', '<=', now()))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $poll): void {
                    $poll->whereNotNull('provider_generation_id')
                        ->whereIn('provider_status', [GenerationStatus::Queued, GenerationStatus::Processing])
                        ->whereNull('reconciliation_stopped_at')
                        ->where(fn (Builder $due) => $due->whereNull('next_reconciliation_attempt_at')->orWhere('next_reconciliation_attempt_at', '<=', now()))
                        ->where(function (Builder $stale): void {
                            $stale->where('last_reconciliation_attempt_at', '<=', now()->subSeconds(120))
                                ->orWhere(fn (Builder $first) => $first->whereNull('last_reconciliation_attempt_at')->where('provider_created_at', '<=', now()->subSeconds(120)));
                        });
                })->orWhere(function (Builder $submission): void {
                    $submission->whereNull('provider_generation_id')
                        ->where(fn (Builder $budget) => $budget->where('submission_attempts', '<', SubmitGeneration::MAX_SUBMISSIONS)->orWhere('submission_status', SubmissionStatus::Submitting))
                        ->where('created_at', '<=', now()->subSeconds(60))
                        ->where(fn (Builder $last) => $last->whereNull('last_submission_attempt_at')->orWhere('last_submission_attempt_at', '<=', now()->subSeconds(60)))
                        ->where(fn (Builder $due) => $due->whereNull('next_submission_attempt_at')->orWhere('next_submission_attempt_at', '<=', now()))
                        ->where(function (Builder $state): void {
                            $state->where('submission_status', SubmissionStatus::Pending)
                                ->orWhere('submission_status', SubmissionStatus::Submitting)
                                ->orWhere(fn (Builder $uncertain) => $uncertain->where('submission_status', SubmissionStatus::Uncertain)
                                    ->where(fn (Builder $error) => $error->whereNull('submission_error_code')->orWhereIn('submission_error_code', ['connection_failure', 'invalid_response', 'provider_error', 'worker_failed', 'outcome_unknown', 'daily_limit_exceeded'])))
                                ->orWhere(fn (Builder $quota) => $quota->where('submission_status', SubmissionStatus::Rejected)
                                    ->where('submission_error_code', 'daily_limit_exceeded')->whereNotNull('next_submission_attempt_at'));
                        });
                });
            });
    }
}
