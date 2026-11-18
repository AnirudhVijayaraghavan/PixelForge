<?php

declare(strict_types=1);

namespace App\Domain\Billing;

final readonly class CalculatedPrice
{
    /** @param array<string, string> $providerBreakdown */
    public function __construct(
        public Money $providerCost,
        public Money $customerCharge,
        public array $providerBreakdown,
    ) {}

    /** @return array{provider_cost_usd: string, customer_charge_usd: string, provider_breakdown_usd: array<string, string>} */
    public function toArray(): array
    {
        return [
            'provider_cost_usd' => $this->providerCost->toUsd(),
            'customer_charge_usd' => $this->customerCharge->toUsd(),
            'provider_breakdown_usd' => $this->providerBreakdown,
        ];
    }
}
