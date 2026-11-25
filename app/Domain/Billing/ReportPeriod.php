<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReportPeriod
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(string $start, string $end)
    {
        foreach ([$start, $end] as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                throw new InvalidArgumentException('Use valid UTC dates in YYYY-MM-DD format.');
            }
        }
        $this->start = CarbonImmutable::parse($start, 'UTC')->startOfDay();
        $this->end = CarbonImmutable::parse($end, 'UTC')->startOfDay();

        if ($this->end->lessThan($this->start) || $this->start->diffInDays($this->end) >= 92) {
            throw new InvalidArgumentException('Select an inclusive date range of at most 92 days.');
        }
    }

    /** @return list<string> */
    public function dates(): array
    {
        $dates = [];
        for ($date = $this->start; $date->lessThanOrEqualTo($this->end); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /** @return array{start_date: string, end_date: string} */
    public function toArray(): array
    {
        return ['start_date' => $this->start->toDateString(), 'end_date' => $this->end->toDateString()];
    }
}
