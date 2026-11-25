<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeException;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use LogicException;

final readonly class ProviderReconciliation
{
    public function __construct(private PixelforgeClient $client, private Repository $cache) {}

    /** Returns a safe error message, or null on success. */
    public function refresh(ReportPeriod $period): ?string
    {
        $scope = $this->scope();
        $store = $this->cache->getStore();
        if (! $store instanceof LockProvider) {
            throw new LogicException('Provider reconciliation requires an atomic-lock cache store.');
        }
        $lock = $store->lock($scope.':lock', 30);
        if (! $lock->get()) {
            return 'A provider usage refresh is already running. Please wait.';
        }

        try {
            $next = $this->cache->get($scope.':next_refresh');
            if (is_string($next) && CarbonImmutable::parse($next)->isFuture()) {
                return 'Refresh is limited for this provider key. Try after '.$next.'.';
            }
            $key = $this->key($period);
            $snapshot = $this->cache->get($key, []);
            $snapshot['last_attempt_at'] = now()->toIso8601String();
            $delay = 60;
            try {
                $snapshot['data'] = $this->client->usage($period)->toArray();
                $snapshot['fetched_at'] = now()->toIso8601String();
                $snapshot['error_code'] = null;
            } catch (PixelforgeException $exception) {
                $snapshot['error_code'] = $exception->providerCode;
                $delay = max(60, $exception->retryAfterSeconds ?? 0);
            }
            $this->cache->put($key, $snapshot, 86400);
            $this->cache->put($scope.':next_refresh', now()->addSeconds($delay)->toIso8601String(), $delay);

            return $snapshot['error_code'] === null ? null : 'Provider usage is unavailable. Previous successful data, if any, is retained as stale.';
        } finally {
            $lock->release();
        }
    }

    /** @param list<array<string, mixed>> $localDays
     * @return array<string, mixed>
     */
    public function compare(ReportPeriod $period, array $localDays): array
    {
        $snapshot = $this->cache->get($this->key($period), []);
        $fetchedAt = $snapshot['fetched_at'] ?? null;
        $stale = is_string($fetchedAt) && CarbonImmutable::parse($fetchedAt)->lessThanOrEqualTo(now()->subMinutes(10));
        $unavailable = isset($snapshot['error_code']) || $stale;
        $rows = [];
        $localCost = BigInteger::zero();
        foreach ($localDays as $local) {
            $localCost = $localCost->plus($local['provider_cost_nanodollars']);
            $provider = $snapshot['data']['days'][$local['date']] ?? null;
            $status = $unavailable ? 'UNAVAILABLE' : ($provider === null ? 'NOT_CHECKED' : 'MATCHED');
            $differences = [];
            if ($provider !== null) {
                foreach (['generations', ...TokenUsage::CATEGORIES, 'provider_cost_nanodollars'] as $metric) {
                    $difference = (string) BigInteger::of($local[$metric])->minus($provider[$metric]);
                    $differences[$metric] = $difference;
                    if ($status === 'MATCHED' && $difference !== '0') {
                        $status = 'MISMATCHED';
                    }
                }
                if ($status === 'MATCHED' && $local['missing_settlements'] > 0) {
                    $status = 'MISMATCHED';
                }
                $differences['provider_cost_usd'] = FinancialTotals::usd($differences['provider_cost_nanodollars']);
            }
            $rows[] = ['date' => $local['date'], 'status' => $status, 'local' => $local, 'provider' => $provider, 'differences' => $differences];
        }

        $statuses = array_column($rows, 'status');
        $status = in_array('UNAVAILABLE', $statuses, true) ? 'UNAVAILABLE'
            : (in_array('NOT_CHECKED', $statuses, true) ? 'NOT_CHECKED'
                : (in_array('MISMATCHED', $statuses, true) ? 'MISMATCHED' : 'MATCHED'));
        $providerTotal = $snapshot['data']['total'] ?? null;

        return [
            'status' => $status, 'provider_total' => $providerTotal,
            'total_cost_difference_usd' => $providerTotal === null ? null : FinancialTotals::usd((string) $localCost->minus($providerTotal['provider_cost_nanodollars'])),
            'days' => $rows, 'last_successful_fetch_at' => $fetchedAt,
            'last_attempt_at' => $snapshot['last_attempt_at'] ?? null,
            'error_code' => $snapshot['error_code'] ?? null,
            'is_stale' => $stale || ($unavailable && $fetchedAt !== null),
            'next_refresh_at' => $this->cache->get($this->scope().':next_refresh'),
        ];
    }

    private function scope(): string
    {
        return 'pixelforge:usage:v1:'.hash('sha256', json_encode([
            config('app.env'), config('services.pixelforge.base_url'), config('services.pixelforge.api_key'),
        ], JSON_THROW_ON_ERROR));
    }

    private function key(ReportPeriod $period): string
    {
        return $this->scope().':'.$period->start->toDateString().':'.$period->end->toDateString();
    }
}
