<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Brick\Math\BigInteger;
use InvalidArgumentException;

final readonly class TokenUsage
{
    public const array CATEGORIES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    public int $totalTokens;

    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
    ) {
        $total = BigInteger::zero();

        foreach ([$inputTextTokens, $inputImageTokens, $outputTokens, $reasoningTokens] as $tokens) {
            if ($tokens < 0) {
                throw new InvalidArgumentException('Token usage cannot be negative.');
            }

            $total = $total->plus($tokens);
        }

        $this->totalTokens = $total->toInt();
    }

    /** @param array<string, mixed> $usage */
    public static function fromArray(array $usage): self
    {
        $validated = [];

        foreach (self::CATEGORIES as $category) {
            if (! isset($usage[$category]) || ! is_int($usage[$category]) || $usage[$category] < 0) {
                throw new InvalidArgumentException("Token usage must contain a nonnegative integer for {$category}.");
            }

            $validated[$category] = $usage[$category];
        }

        $result = new self($validated['input_text_tokens'], $validated['input_image_tokens'], $validated['output_tokens'], $validated['reasoning_tokens']);

        if (array_key_exists('total_tokens', $usage) && $usage['total_tokens'] !== $result->totalTokens) {
            throw new InvalidArgumentException('Provider total_tokens does not match the category counts.');
        }

        return $result;
    }

    /** @return array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int, total_tokens: int} */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}
