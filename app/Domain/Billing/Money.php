<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\IntegerOverflowException;
use InvalidArgumentException;
use JsonSerializable;
use OverflowException;

final readonly class Money implements JsonSerializable
{
    private function __construct(private int $amount) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromUsd(string $amount): self
    {
        if (! preg_match('/^-?\d+(?:\.\d{1,9})?$/D', $amount)) {
            throw new InvalidArgumentException('USD must be a decimal string with at most nine fractional digits.');
        }

        return self::fromNanodollars((string) BigDecimal::of($amount)->multipliedBy('1000000000')->toBigInteger());
    }

    public static function fromNanodollars(int|string $amount): self
    {
        if (is_string($amount) && ! preg_match('/^-?\d+$/D', $amount)) {
            throw new InvalidArgumentException('Nanodollars must be an integer.');
        }

        $integer = BigInteger::of($amount);

        if ($integer->isLessThan('-9223372036854775808') || $integer->isGreaterThan('9223372036854775807')) {
            throw new OverflowException('Money exceeds the signed BIGINT nanodollar range.');
        }

        try {
            return new self($integer->toInt());
        } catch (IntegerOverflowException $exception) {
            throw new OverflowException('This runtime cannot represent the nanodollar amount.', previous: $exception);
        }
    }

    public function nanodollars(): int
    {
        return $this->amount;
    }

    public function toUsd(): string
    {
        return (string) BigDecimal::ofUnscaledValue($this->amount, 9);
    }

    public function add(self $other): self
    {
        return self::fromNanodollars((string) BigInteger::of($this->amount)->plus($other->amount));
    }

    public function subtract(self $other): self
    {
        return self::fromNanodollars((string) BigInteger::of($this->amount)->minus($other->amount));
    }

    public function compareTo(self $other): int
    {
        return $this->amount <=> $other->amount;
    }

    public function jsonSerialize(): string
    {
        return $this->toUsd();
    }
}
