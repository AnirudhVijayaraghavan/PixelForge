<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use InvalidArgumentException;

/** Arbitrary-precision report totals must not be narrowed to Money's per-record BIGINT. */
final class FinancialTotals
{
    /** @var array<string, string> */
    private array $values;

    public function __construct()
    {
        $this->values = array_fill_keys(['generations', ...TokenUsage::CATEGORIES, 'provider_cost_nanodollars', 'customer_charge_nanodollars'], '0');
    }

    /** @param array<string, int> $usage */
    public function add(string $providerCost, string $customerCharge, array $usage): void
    {
        $amounts = [
            'generations' => '1',
            'provider_cost_nanodollars' => (string) Money::fromNanodollars($providerCost)->nanodollars(),
            'customer_charge_nanodollars' => (string) Money::fromNanodollars($customerCharge)->nanodollars(),
        ];
        foreach (TokenUsage::CATEGORIES as $category) {
            $amounts[$category] = (string) $usage[$category];
        }
        foreach ($amounts as $key => $value) {
            if (BigInteger::of($value)->isNegative()) {
                throw new InvalidArgumentException('Financial reports require nonnegative confirmed amounts.');
            }
            $this->values[$key] = (string) BigInteger::of($this->values[$key])->plus($value);
        }
    }

    public static function usd(string $nanodollars): string
    {
        return (string) BigDecimal::ofUnscaledValue(BigInteger::of($nanodollars), 9);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->values + [
            'provider_cost_usd' => self::usd($this->values['provider_cost_nanodollars']),
            'customer_charge_usd' => self::usd($this->values['customer_charge_nanodollars']),
            'gross_profit_usd' => self::usd((string) BigInteger::of($this->values['customer_charge_nanodollars'])->minus($this->values['provider_cost_nanodollars'])),
        ];
    }
}
