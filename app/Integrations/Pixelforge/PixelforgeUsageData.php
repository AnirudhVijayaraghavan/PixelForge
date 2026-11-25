<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use App\Domain\Billing\FinancialTotals;
use App\Domain\Billing\ReportPeriod;
use App\Domain\Billing\TokenUsage;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use InvalidArgumentException;

final readonly class PixelforgeUsageData
{
    /** @param array<string, array<string, string>> $days
     * @param  array<string, string>  $total
     */
    private function __construct(public array $days, public array $total) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ReportPeriod $period): self
    {
        if (($data['object'] ?? null) !== 'usage' || ($data['start_date'] ?? null) !== $period->start->toDateString()
            || ($data['end_date'] ?? null) !== $period->end->toDateString()
            || ! is_array($data['data'] ?? null) || ! array_is_list($data['data']) || ! is_array($data['total'] ?? null)) {
            throw new InvalidArgumentException('Invalid provider usage envelope.');
        }
        $dates = $period->dates();
        $days = [];
        $summed = array_fill_keys(['generations', ...TokenUsage::CATEGORIES, 'provider_cost_nanodollars'], '0');
        foreach ($data['data'] as $day) {
            if (! is_array($day) || ! is_string($day['date'] ?? null)
                || ! in_array($day['date'], $dates, true) || isset($days[$day['date']])) {
                throw new InvalidArgumentException('Invalid or duplicate usage day.');
            }
            $metrics = self::metrics($day);
            $days[$day['date']] = $metrics;
            foreach ($summed as $key => $value) {
                $summed[$key] = (string) BigInteger::of($value)->plus($metrics[$key]);
            }
        }
        if (count($days) !== count($dates)) {
            throw new InvalidArgumentException('Provider usage must include every requested UTC date.');
        }
        $total = self::metrics($data['total']);
        foreach ($summed as $key => $value) {
            if ($total[$key] !== $value) {
                throw new InvalidArgumentException('Provider usage totals do not reconcile with its days.');
            }
        }
        ksort($days);

        return new self($days, $total);
    }

    /** @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function metrics(array $data): array
    {
        $metrics = [];
        foreach (['generations', ...TokenUsage::CATEGORIES] as $key) {
            if (! is_int($data[$key] ?? null) || $data[$key] < 0) {
                throw new InvalidArgumentException('Usage counts must be nonnegative integers.');
            }
            $metrics[$key] = (string) $data[$key];
        }
        if (! is_string($data['cost_usd'] ?? null) || ! preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{9}$/D', $data['cost_usd'])) {
            throw new InvalidArgumentException('Usage cost must be an exact nine-decimal USD string.');
        }
        $metrics['provider_cost_nanodollars'] = (string) BigDecimal::of($data['cost_usd'])->multipliedBy('1000000000')->toBigInteger();
        $metrics['provider_cost_usd'] = FinancialTotals::usd($metrics['provider_cost_nanodollars']);

        return $metrics;
    }

    /** @return array{days: array<string, array<string, string>>, total: array<string, string>} */
    public function toArray(): array
    {
        return ['days' => $this->days, 'total' => $this->total];
    }
}
