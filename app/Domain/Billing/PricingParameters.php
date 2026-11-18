<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class PricingParameters
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens
     */
    public function __construct(
        public int $charactersPerTextToken,
        public string $textTokenVariance,
        public int $tokensPerReferenceImage,
        private array $outputTokens,
        private array $reasoningTokens,
    ) {
        if ($charactersPerTextToken < 1 || $tokensPerReferenceImage < 0
            || ! preg_match('/^\d+(?:\.\d+)?$/D', $textTokenVariance)
            || BigDecimal::of($textTokenVariance)->isGreaterThan(1)) {
            throw new InvalidArgumentException('Invalid provider token estimation parameters.');
        }

        foreach (GenerationQuality::cases() as $quality) {
            foreach (GenerationSize::cases() as $size) {
                $output = $outputTokens[$quality->value][$size->value] ?? null;

                if (! is_int($output) || $output < 0) {
                    throw new InvalidArgumentException('Every supported quality and size needs a nonnegative output token count.');
                }
            }

            $bounds = $reasoningTokens[$quality->value] ?? null;

            if ($bounds === null || $bounds['min'] < 0 || $bounds['max'] < $bounds['min']) {
                throw new InvalidArgumentException('Invalid provider reasoning token bounds.');
            }
        }
    }

    /** @param array<string, mixed> $pricing */
    public static function fromPricingResponse(array $pricing): self
    {
        if (($pricing['currency'] ?? null) !== 'usd' || ($pricing['unit'] ?? null) !== 'per_1m_tokens') {
            throw new InvalidArgumentException('Only USD pricing per million tokens is supported.');
        }

        $tokens = $pricing['tokens'] ?? null;

        if (! is_array($tokens)
            || ! is_int($tokens['characters_per_text_token'] ?? null)
            || ! is_int($tokens['per_reference_image'] ?? null)
            || ! is_array($tokens['output'] ?? null)
            || ! is_array($tokens['reasoning'] ?? null)) {
            throw new InvalidArgumentException('Incomplete provider token pricing parameters.');
        }

        $variance = $tokens['text_token_variance'] ?? null;

        /** The provider encodes this non-monetary ratio as a JSON number; no float arithmetic is used. */
        if (is_int($variance) || is_float($variance)) {
            $variance = json_encode($variance, JSON_THROW_ON_ERROR);
        }

        if (! is_string($variance)) {
            throw new InvalidArgumentException('Missing provider text token variance.');
        }

        $output = [];
        $reasoning = [];

        foreach (GenerationQuality::cases() as $quality) {
            $qualityOutput = $tokens['output'][$quality->value] ?? null;
            $bounds = $tokens['reasoning'][$quality->value] ?? null;

            if (! is_array($qualityOutput) || ! is_array($bounds)
                || ! is_int($bounds['min'] ?? null) || ! is_int($bounds['max'] ?? null)) {
                throw new InvalidArgumentException('Incomplete provider quality token parameters.');
            }

            foreach (GenerationSize::cases() as $size) {
                $count = $qualityOutput[$size->value] ?? null;

                if (! is_int($count)) {
                    throw new InvalidArgumentException('Provider output token counts must be integers.');
                }

                $output[$quality->value][$size->value] = $count;
            }

            $reasoning[$quality->value] = ['min' => $bounds['min'], 'max' => $bounds['max']];
        }

        return new self($tokens['characters_per_text_token'], $variance, $tokens['per_reference_image'], $output, $reasoning);
    }

    public function outputTokens(GenerationQuality $quality, GenerationSize $size): int
    {
        return $this->outputTokens[$quality->value][$size->value];
    }

    /** @return array{min: int, max: int} */
    public function reasoningBounds(GenerationQuality $quality): array
    {
        return $this->reasoningTokens[$quality->value];
    }

    /** @return array{characters_per_text_token: int, text_token_variance: string, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>} */
    public function toArray(): array
    {
        return [
            'characters_per_text_token' => $this->charactersPerTextToken,
            'text_token_variance' => $this->textTokenVariance,
            'per_reference_image' => $this->tokensPerReferenceImage,
            'output' => $this->outputTokens,
            'reasoning' => $this->reasoningTokens,
        ];
    }
}
