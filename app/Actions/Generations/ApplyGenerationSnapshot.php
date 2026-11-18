<?php

namespace App\Actions\Generations;

use App\Domain\Billing\GenerationPricing;
use App\Domain\Billing\PricingParameters;
use App\Domain\Billing\ProviderRates;
use App\Domain\Billing\TokenUsage;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class ApplyGenerationSnapshot
{
    public function handle(string $generationId, PixelforgeGenerationData $snapshot): Generation
    {
        return DB::transaction(function () use ($generationId, $snapshot): Generation {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generationId);
            $this->assertIdentity($generation, $snapshot);

            $current = $generation->provider_status;

            if ($current === GenerationStatus::Succeeded) {
                if ($snapshot->status === GenerationStatus::Succeeded && $generation->actual_token_usage !== $snapshot->usage?->toArray()) {
                    throw new UnexpectedValueException('A successful snapshot cannot replace confirmed usage.');
                }

                $this->settle($generation);

                return $generation->load('settlement');
            }

            if ($current === GenerationStatus::Failed
                || ($current === GenerationStatus::Processing && $snapshot->status === GenerationStatus::Queued)) {
                return $generation->load('settlement');
            }

            $generation->provider_generation_id = $snapshot->id;
            $generation->accepted_provider_rates ??= $snapshot->rates->toArray();
            $generation->submission_status = SubmissionStatus::Submitted;
            $generation->provider_status = $snapshot->status;
            $generation->provider_created_at ??= $snapshot->createdAt;
            $generation->provider_started_at ??= $snapshot->startedAt;
            $generation->provider_completed_at = $snapshot->completedAt;
            $generation->image_url = $snapshot->imageUrl;
            $generation->image_url_expires_at = $snapshot->imageUrlExpiresAt;
            $generation->provider_error_code = $snapshot->errorCode;
            $generation->provider_error_message = $snapshot->errorMessage;
            $generation->actual_token_usage = $snapshot->usage?->toArray();
            $generation->submission_error_code = null;
            $generation->submission_error_message = null;
            $generation->next_submission_attempt_at = null;
            $generation->recovery_queued_until = null;
            $generation->recovery_dispatches = 0;
            $generation->next_reconciliation_attempt_at = in_array($snapshot->status, [GenerationStatus::Queued, GenerationStatus::Processing], true)
                ? now()->addSeconds(120) : null;
            $generation->reconciliation_error_code = null;
            $generation->reconciliation_error_message = null;
            $generation->reconciliation_stopped_at = null;

            /** Save succeeded first: the settlement's composite foreign key requires it. */
            $generation->save();

            if ($snapshot->status === GenerationStatus::Succeeded) {
                $this->settle($generation);
            }

            return $generation->load('settlement');
        });
    }

    public function assertIdentity(Generation $generation, PixelforgeGenerationData $snapshot): void
    {
        if (($snapshot->metadata['local_generation_id'] ?? null) !== $generation->id
            || $snapshot->metadata != $generation->request_payload['metadata']
            || $snapshot->prompt !== $generation->prompt
            || $snapshot->size !== $generation->size || $snapshot->quality !== $generation->quality
            || $snapshot->referenceImages !== $generation->reference_images
            || ($generation->provider_generation_id !== null && $generation->provider_generation_id !== $snapshot->id)
            || ($generation->provider_generation_id === null && $generation->submission_status === SubmissionStatus::Rejected)) {
            throw new UnexpectedValueException('Provider snapshot identity does not match the local generation.');
        }

        if ($generation->accepted_provider_rates !== null) {
            foreach (ProviderRates::fromArray($generation->accepted_provider_rates)->toArray() as $category => $rate) {
                if (! BigDecimal::of($rate)->isEqualTo($snapshot->rates->toArray()[$category])) {
                    throw new UnexpectedValueException('A provider snapshot cannot replace accepted rates.');
                }
            }
        }
    }

    /** The caller holds the generation row lock; settlement facts are immutable. */
    private function settle(Generation $generation): void
    {
        $settlement = $generation->settlement()->first();

        if ($settlement === null) {
            if ($generation->actual_token_usage === null || $generation->accepted_provider_rates === null) {
                throw new UnexpectedValueException('Successful generations require confirmed usage and accepted rates.');
            }

            $pricing = new GenerationPricing(PricingParameters::fromPricingResponse($generation->quote_snapshot));
            $price = $pricing->calculateActual(TokenUsage::fromArray($generation->actual_token_usage), ProviderRates::fromArray($generation->accepted_provider_rates));
            $settlement = new GenerationSettlement;
            $settlement->generation_id = $generation->id;
            $settlement->provider_status = GenerationStatus::Succeeded;
            $settlement->provider_cost_nanodollars = (string) $price->providerCost->nanodollars();
            $settlement->customer_charge_nanodollars = (string) $price->customerCharge->nanodollars();
            $settlement->settled_at = now();
            $settlement->save();
        }

        if ($generation->settled_at === null || ! $generation->settled_at->equalTo($settlement->settled_at)) {
            $generation->settled_at = $settlement->settled_at;
            $generation->save();
        }
    }
}
