<?php

namespace App\Http\Resources;

use App\Domain\Billing\GenerationPricing;
use App\Domain\Billing\Money;
use App\Domain\Billing\PricingParameters;
use App\Domain\Billing\ProviderRates;
use App\Domain\Billing\TokenUsage;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class GenerationResource extends JsonResource
{
    public function __construct(private Generation $generation)
    {
        parent::__construct($generation);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $generation = $this->generation;

        if (! $generation->relationLoaded('settlement')) {
            throw new LogicException('Generation presentation requires eager-loaded settlement data.');
        }

        $quotaRetry = $generation->submission_status === SubmissionStatus::Rejected
            && $generation->submission_error_code === 'daily_limit_exceeded' && $generation->next_submission_attempt_at !== null;
        $status = $generation->provider_status !== null ? $generation->provider_status->value : match (true) {
            $quotaRetry, $generation->submission_status === SubmissionStatus::Pending && $generation->submission_attempts > 0 => 'retrying',
            default => $generation->submission_status->value,
        };
        $billingState = match (true) {
            $generation->provider_status === GenerationStatus::Succeeded => $generation->settlement === null ? 'unavailable' : 'settled',
            $generation->provider_status === GenerationStatus::Failed,
            $generation->submission_status === SubmissionStatus::Rejected && ! $quotaRetry => 'free',
            default => 'pending',
        };
        $imageState = match (true) {
            $generation->provider_status !== GenerationStatus::Succeeded => 'pending',
            $generation->image_url_expires_at?->lessThanOrEqualTo(now()) => 'expired',
            ! $this->safeImageUrl($generation->image_url) => 'unavailable',
            default => 'available',
        };

        return [
            'id' => $generation->id,
            'prompt' => $generation->prompt,
            'prompt_excerpt' => Str::limit($generation->prompt, 120),
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'status' => $status,
            'status_label' => match ($status) {
                'pending' => 'Waiting to submit', 'submitting' => 'Submitting', 'retrying' => 'Retry scheduled',
                'uncertain' => 'Verifying status', 'rejected' => 'Not accepted', 'queued' => 'Queued',
                'processing' => 'Creating', 'succeeded' => 'Completed', 'failed' => 'Failed',
                default => 'Verifying status',
            },
            'status_message' => $this->statusMessage($status),
            'active' => ! in_array($status, ['succeeded', 'failed', 'rejected'], true),
            'billing_state' => $billingState,
            'estimated_customer_charge_usd' => Money::fromNanodollars($generation->estimated_customer_charge_nanodollars)->toUsd(),
            'actual_customer_charge_usd' => $generation->settlement !== null
                ? Money::fromNanodollars($generation->settlement->customer_charge_nanodollars)->toUsd()
                : ($billingState === 'free' ? Money::zero()->toUsd() : null),
            'image_url' => $imageState === 'available' ? $generation->image_url : null,
            'image_state' => $imageState,
            'image_url_expires_at' => $generation->image_url_expires_at?->toIso8601String(),
            'created_at' => $generation->created_at->toIso8601String(),
            'updated_at' => $generation->updated_at->toIso8601String(),
            'provider_created_at' => $generation->provider_created_at?->toIso8601String(),
            'started_at' => $generation->provider_started_at?->toIso8601String(),
            'completed_at' => $generation->provider_completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Request $request): array
    {
        $summary = $this->toArray($request);

        return [
            ...$summary,
            'reference_images' => $this->generation->reference_images,
            'billing' => $this->billing($summary['billing_state']),
        ];
    }

    /** @return array<string, mixed> */
    private function billing(string $state): array
    {
        $generation = $this->generation;
        $settlement = $generation->settlement;
        $billing = [
            'state' => $state, 'rows' => [], 'total_tokens' => null,
            'provider_cost_usd' => $settlement !== null ? Money::fromNanodollars($settlement->provider_cost_nanodollars)->toUsd() : null,
            'customer_charge_usd' => $settlement !== null ? Money::fromNanodollars($settlement->customer_charge_nanodollars)->toUsd() : ($state === 'free' ? Money::zero()->toUsd() : null),
            'markup_usd' => null, 'difference_usd' => null,
            'estimated_customer_charge_usd' => Money::fromNanodollars($generation->estimated_customer_charge_nanodollars)->toUsd(),
            'markup_percentage' => '25%',
            'rate_provenance' => 'Accepted generation rates; frozen at provider acceptance.',
        ];

        if ($state !== 'settled' || $settlement === null) {
            return $billing;
        }

        try {
            if ($generation->actual_token_usage === null || $generation->accepted_provider_rates === null) {
                throw new LogicException('Confirmed billing data is missing.');
            }

            $usage = TokenUsage::fromArray($generation->actual_token_usage);
            $rates = ProviderRates::fromArray($generation->accepted_provider_rates);
            $calculated = (new GenerationPricing(PricingParameters::fromPricingResponse($generation->quote_snapshot)))->calculateActual($usage, $rates);
            $providerCost = Money::fromNanodollars($settlement->provider_cost_nanodollars);
            $customerCharge = Money::fromNanodollars($settlement->customer_charge_nanodollars);

            if ($calculated->providerCost->compareTo($providerCost) !== 0 || $calculated->customerCharge->compareTo($customerCharge) !== 0) {
                throw new LogicException('Settlement does not reconcile with confirmed billing data.');
            }

            $labels = ['input_text_tokens' => 'Input text', 'input_image_tokens' => 'Reference images', 'output_tokens' => 'Image output', 'reasoning_tokens' => 'Reasoning'];
            foreach ($rates->toArray() as $category => $rate) {
                $billing['rows'][] = ['category' => $category, 'label' => $labels[$category], 'tokens' => $usage->toArray()[$category], 'rate_usd' => $rate, 'cost_usd' => $calculated->providerBreakdown[$category]];
            }

            $billing['total_tokens'] = $usage->totalTokens;
            $billing['markup_usd'] = $customerCharge->subtract($providerCost)->toUsd();
            $billing['difference_usd'] = $customerCharge->subtract(Money::fromNanodollars($generation->estimated_customer_charge_nanodollars))->toUsd();
        } catch (Throwable) {
            $billing['state'] = 'unavailable';
        }

        return $billing;
    }

    private function statusMessage(string $status): string
    {
        if ($this->generation->reconciliation_stopped_at !== null && in_array($status, ['queued', 'processing'], true)) {
            return 'Status verification needs review. This is not a confirmed failure.';
        }

        return match ($status) {
            'pending' => 'Your request is saved and waiting for the submission worker.',
            'submitting' => 'Sending your saved request to Pixelforge.',
            'retrying' => 'Submission will retry after the provider limit clears. The final charge is pending.',
            'uncertain' => 'The provider may have accepted this request. We are verifying its status; do not submit it again.',
            'rejected' => match ($this->generation->submission_error_code) {
                'content_policy_violation' => 'The provider declined this prompt under its content policy. You were not charged.',
                'validation_failed' => 'The provider could not accept these settings. You were not charged.',
                default => 'The provider did not accept this request. You were not charged.',
            },
            'queued' => 'Accepted by Pixelforge and waiting to start.',
            'processing' => 'Your image is being created. Detailed generations can take 60–90 seconds.',
            'succeeded' => 'Your image is ready. Final billing uses confirmed token usage.',
            'failed' => 'The provider could not complete this image. You were not charged.',
            default => 'The latest status is being verified.',
        };
    }

    private function safeImageUrl(?string $url): bool
    {
        return $url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
