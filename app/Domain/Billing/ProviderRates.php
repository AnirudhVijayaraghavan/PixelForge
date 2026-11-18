<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use InvalidArgumentException;

final readonly class ProviderRates
{
    public function __construct(
        public string $inputTextTokens,
        public string $inputImageTokens,
        public string $outputTokens,
        public string $reasoningTokens,
    ) {
        foreach ($this->toArray() as $rate) {
            if (! preg_match('/^\d+(?:\.\d+)?$/D', $rate)) {
                throw new InvalidArgumentException('Provider rates must be nonnegative exact decimal strings.');
            }
        }
    }

    /** @param array<string, mixed> $rates */
    public static function fromArray(array $rates): self
    {
        $validated = [];

        foreach (TokenUsage::CATEGORIES as $category) {
            if (! isset($rates[$category]) || ! is_string($rates[$category])) {
                throw new InvalidArgumentException("Missing or non-string provider rate: {$category}.");
            }

            $validated[$category] = $rates[$category];
        }

        return new self($validated['input_text_tokens'], $validated['input_image_tokens'], $validated['output_tokens'], $validated['reasoning_tokens']);
    }

    /** @return array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string} */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
