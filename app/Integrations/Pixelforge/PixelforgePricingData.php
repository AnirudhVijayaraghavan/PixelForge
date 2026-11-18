<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use App\Domain\Billing\PricingParameters;
use App\Domain\Billing\ProviderRates;
use InvalidArgumentException;

final readonly class PixelforgePricingData
{
    private function __construct(
        public ProviderRates $rates,
        public PricingParameters $parameters,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (($data['object'] ?? null) !== 'pricing' || ! is_array($data['rates'] ?? null)) {
            throw new InvalidArgumentException('Invalid pricing object.');
        }

        return new self(ProviderRates::fromArray($data['rates']), PricingParameters::fromPricingResponse($data));
    }
}
