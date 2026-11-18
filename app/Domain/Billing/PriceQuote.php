<?php

declare(strict_types=1);

namespace App\Domain\Billing;

final readonly class PriceQuote
{
    public function __construct(
        public TokenUsage $estimatedUsage,
        public TokenUsage $minimumUsage,
        public TokenUsage $maximumUsage,
        public CalculatedPrice $estimate,
        public CalculatedPrice $minimum,
        public CalculatedPrice $maximum,
        public ProviderRates $rates,
        public PricingParameters $parameters,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency' => 'usd',
            'unit' => 'per_1m_tokens',
            'markup' => '1.25',
            'rounding' => 'HALF_UP_9_DECIMALS',
            'reasoning_estimate_policy' => 'midpoint_ceiling',
            'estimated_usage' => $this->estimatedUsage->toArray(),
            'minimum_usage' => $this->minimumUsage->toArray(),
            'maximum_usage' => $this->maximumUsage->toArray(),
            'estimate' => $this->estimate->toArray(),
            'minimum' => $this->minimum->toArray(),
            'maximum' => $this->maximum->toArray(),
            'rates' => $this->rates->toArray(),
            'tokens' => $this->parameters->toArray(),
        ];
    }
}
