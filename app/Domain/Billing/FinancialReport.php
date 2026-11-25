<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class FinancialReport
{
    /** @return array<string, mixed> */
    public function build(ReportPeriod $period): array
    {
        $users = $this->settlements($period)->join('users as u', 'u.id', '=', 'g.user_id')
            ->select('u.id as user_id', 'u.name', 'u.email')
            ->groupBy('u.id', 'u.name', 'u.email')->orderBy('u.id')
            ->paginate(15, ['*'], 'users_page');
        $groups = $this->settlements($period)->join('users as u', 'u.id', '=', 'g.user_id')
            ->select('u.id as user_id', 'u.name')->selectRaw('DATE(g.provider_completed_at) as date')
            ->groupBy('u.id', 'u.name', DB::raw('DATE(g.provider_completed_at)'))
            ->orderByDesc('date')->orderBy('u.id')->paginate(15, ['*'], 'breakdown_page');

        $userTotals = [];
        foreach ($users->items() as $user) {
            $userTotals[$user->user_id] = new FinancialTotals;
        }
        $groupTotals = [];
        foreach ($groups->items() as $group) {
            $groupTotals[$group->date.':'.$group->user_id] = new FinancialTotals;
        }
        $daily = [];
        foreach ($period->dates() as $date) {
            $daily[$date] = new FinancialTotals;
        }
        $total = new FinancialTotals;

        /** One bounded-memory scan; no SQL SUM overflow, floating aggregates, or per-user queries. */
        $rows = $this->settlements($period)->select([
            's.id as settlement_id', 's.provider_cost_nanodollars', 's.customer_charge_nanodollars',
            'g.user_id', 'g.provider_completed_at', 'g.actual_token_usage',
        ])->lazyById(500, 's.id', 'settlement_id');
        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->provider_completed_at, 'UTC')->toDateString();
            $usage = TokenUsage::fromArray(json_decode($row->actual_token_usage, true, flags: JSON_THROW_ON_ERROR))->toArray();
            $targets = [$total, $daily[$date]];
            if (isset($userTotals[$row->user_id])) {
                $targets[] = $userTotals[$row->user_id];
            }
            if (isset($groupTotals[$date.':'.$row->user_id])) {
                $targets[] = $groupTotals[$date.':'.$row->user_id];
            }
            foreach ($targets as $target) {
                $target->add((string) $row->provider_cost_nanodollars, (string) $row->customer_charge_nanodollars, $usage);
            }
        }

        $missing = $this->completed($period)->leftJoin('generation_settlements as s', 's.generation_id', '=', 'g.id')
            ->where('g.provider_status', 'succeeded')->whereNull('s.id')
            ->selectRaw('DATE(g.provider_completed_at) as date, COUNT(*) as missing')
            ->groupByRaw('DATE(g.provider_completed_at)')->pluck('missing', 'date');
        $days = [];
        foreach ($daily as $date => $totals) {
            $days[] = ['date' => $date, ...$totals->toArray(), 'missing_settlements' => (int) ($missing[$date] ?? 0)];
        }

        $users->through(fn (stdClass $row): array => [
            'user_id' => (int) $row->user_id, 'name' => $row->name, 'email' => $row->email,
            ...$userTotals[$row->user_id]->toArray(),
        ]);
        $groups->through(fn (stdClass $row): array => [
            'date' => $row->date, 'user_id' => (int) $row->user_id, 'name' => $row->name,
            ...$groupTotals[$row->date.':'.$row->user_id]->toArray(),
        ]);
        $generations = Generation::query()->with(['user:id,name', 'settlement'])
            ->where('provider_completed_at', '>=', $period->start->toDateTimeString())
            ->where('provider_completed_at', '<', $period->end->addDay()->toDateTimeString())
            ->orderByDesc('provider_completed_at')->orderByDesc('id')->paginate(20, ['*'], 'generations_page');
        $generations->through(fn (Generation $generation): array => [
            'id' => $generation->id,
            'provider_generation_id' => $generation->provider_generation_id,
            'user_name' => $generation->user->name,
            'completed_at' => $generation->provider_completed_at?->toIso8601String(),
            'status' => $generation->provider_status?->value,
            'provider_cost_usd' => $generation->settlement !== null ? Money::fromNanodollars($generation->settlement->provider_cost_nanodollars)->toUsd() : ($generation->provider_status?->value === 'failed' ? '0.000000000' : null),
            'customer_charge_usd' => $generation->settlement !== null ? Money::fromNanodollars($generation->settlement->customer_charge_nanodollars)->toUsd() : ($generation->provider_status?->value === 'failed' ? '0.000000000' : null),
        ]);

        return [
            'summary' => $total->toArray() + ['missing_settlements' => (int) $missing->sum()],
            'daily' => $days, 'users' => $this->page($users), 'breakdown' => $this->page($groups),
            'generations' => $this->page($generations),
        ];
    }

    private function completed(ReportPeriod $period): Builder
    {
        return DB::table('generations as g')
            ->where('g.provider_completed_at', '>=', $period->start->toDateTimeString())
            ->where('g.provider_completed_at', '<', $period->end->addDay()->toDateTimeString());
    }

    private function settlements(ReportPeriod $period): Builder
    {
        return $this->completed($period)->join('generation_settlements as s', 's.generation_id', '=', 'g.id')
            ->where('g.provider_status', 'succeeded');
    }

    /** @param LengthAwarePaginator<int, mixed> $paginator
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(), 'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(), 'total' => $paginator->total(),
        ];
    }
}
