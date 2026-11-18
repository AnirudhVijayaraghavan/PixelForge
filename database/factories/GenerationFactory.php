<?php

namespace Database\Factories;

use App\Domain\Billing\GenerationPricing;
use App\Domain\Billing\PricingParameters;
use App\Domain\Billing\ProviderRates;
use App\Domain\Billing\TokenUsage;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Generation> */
class GenerationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $id = (string) Str::uuid();
        $prompt = 'An abstract landscape';
        $rates = new ProviderRates('5.00', '10.00', '40.00', '12.50');
        $parameters = new PricingParameters(4, '0.15', 765, [
            'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
            'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
            'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
        ], [
            'low' => ['min' => 0, 'max' => 500],
            'medium' => ['min' => 200, 'max' => 2000],
            'high' => ['min' => 1000, 'max' => 8000],
        ]);
        $quote = (new GenerationPricing($parameters))->quote($prompt, GenerationSize::Square, GenerationQuality::Medium, [], $rates);

        return [
            'id' => $id,
            'user_id' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'request_payload' => [
                'prompt' => $prompt,
                'size' => GenerationSize::Square->value,
                'quality' => GenerationQuality::Medium->value,
                'reference_images' => [],
                'metadata' => ['local_generation_id' => $id],
            ],
            'prompt' => $prompt,
            'size' => GenerationSize::Square,
            'quality' => GenerationQuality::Medium,
            'reference_images' => [],
            'submission_status' => SubmissionStatus::Pending,
            'estimated_provider_cost_nanodollars' => $quote->estimate->providerCost->nanodollars(),
            'estimated_customer_charge_nanodollars' => $quote->estimate->customerCharge->nanodollars(),
            'maximum_customer_charge_nanodollars' => $quote->maximum->customerCharge->nanodollars(),
            'quote_snapshot' => $quote->toArray(),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'submission_status' => SubmissionStatus::Submitted,
            'provider_generation_id' => 'gen_'.Str::ulid(),
            'provider_status' => GenerationStatus::Queued,
            'accepted_provider_rates' => $attributes['quote_snapshot']['rates'],
            'provider_created_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->accepted()->state(fn (): array => [
            'provider_status' => GenerationStatus::Succeeded,
            'actual_token_usage' => (new TokenUsage(6, 0, 1100, 1100))->toArray(),
            'provider_started_at' => now(),
            'provider_completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->accepted()->state(fn (): array => [
            'provider_status' => GenerationStatus::Failed,
            'provider_completed_at' => now(),
            'provider_error_code' => 'generation_failed',
            'provider_error_message' => 'The provider could not produce an image.',
        ]);
    }
}
