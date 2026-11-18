<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class GenerationPricing
{
    public function __construct(private PricingParameters $parameters) {}

    /** @param list<string> $referenceImages */
    public function quote(
        string $prompt,
        GenerationSize $size,
        GenerationQuality $quality,
        array $referenceImages,
        ProviderRates $rates,
    ): PriceQuote {
        if (! mb_check_encoding($prompt, 'UTF-8')) {
            throw new InvalidArgumentException('The prompt must contain valid UTF-8.');
        }

        $characters = mb_strlen($prompt, 'UTF-8');

        if ($characters < 1 || $characters > 4000 || count($referenceImages) > 4) {
            throw new InvalidArgumentException('A prompt needs 1 to 4000 Unicode characters and at most four reference URLs.');
        }

        foreach ($referenceImages as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false
                || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                throw new InvalidArgumentException('Reference images must be HTTP(S) URLs.');
            }
        }

        $text = BigInteger::of($characters)->dividedBy($this->parameters->charactersPerTextToken, RoundingMode::Ceiling)->toInt();
        $variance = BigDecimal::of($this->parameters->textTokenVariance);
        $minimumText = BigDecimal::of($text)->multipliedBy(BigDecimal::one()->minus($variance))->toScale(0, RoundingMode::Floor)->toInt();
        $maximumText = BigDecimal::of($text)->multipliedBy(BigDecimal::one()->plus($variance))->toScale(0, RoundingMode::Ceiling)->toInt();
        $image = BigInteger::of(count($referenceImages))->multipliedBy($this->parameters->tokensPerReferenceImage)->toInt();
        $output = $this->parameters->outputTokens($quality, $size);
        $reasoning = $this->parameters->reasoningBounds($quality);
        $midpoint = BigInteger::of($reasoning['min'])->plus($reasoning['max'])->dividedBy(2, RoundingMode::Ceiling)->toInt();
        $estimatedUsage = new TokenUsage($text, $image, $output, $midpoint);
        $minimumUsage = new TokenUsage($minimumText, $image, $output, $reasoning['min']);
        $maximumUsage = new TokenUsage($maximumText, $image, $output, $reasoning['max']);

        return new PriceQuote(
            $estimatedUsage,
            $minimumUsage,
            $maximumUsage,
            $this->calculateActual($estimatedUsage, $rates),
            $this->calculateActual($minimumUsage, $rates),
            $this->calculateActual($maximumUsage, $rates),
            $rates,
            $this->parameters,
        );
    }

    public function calculateActual(TokenUsage $usage, ProviderRates $acceptedRates): CalculatedPrice
    {
        $total = BigDecimal::zero();
        $breakdown = [];
        $counts = $usage->toArray();

        foreach ($acceptedRates->toArray() as $category => $rate) {
            $cost = BigDecimal::of($rate)->multipliedBy($counts[$category])->dividedByExact('1000000');
            $total = $total->plus($cost);
            $breakdown[$category] = (string) $cost->toScale(max(9, $cost->getScale()));
        }

        try {
            $providerCost = Money::fromUsd((string) $total->toScale(9));
        } catch (RoundingNecessaryException $exception) {
            throw new InvalidArgumentException('Provider cost cannot be represented exactly in nanodollars.', previous: $exception);
        }

        /** Mark up the total once; only the final customer charge is rounded. */
        $customerCharge = Money::fromUsd((string) $total->multipliedBy('1.25')->toScale(9, RoundingMode::HalfUp));

        return new CalculatedPrice($providerCost, $customerCharge, $breakdown);
    }
}
