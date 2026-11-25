<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatGenerationDate } from '@/lib/generations';
import type { Reconciliation } from '@/types/financial-report';

defineProps<{
    reconciliation: Reconciliation;
    refreshed: boolean;
    processing: boolean;
    error?: string;
}>();
defineEmits<{ refresh: [] }>();
</script>
<template>
    <section
        class="space-y-4 rounded-xl border bg-card p-4 sm:p-6"
        aria-labelledby="reconciliation-title"
        :aria-busy="processing"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="reconciliation-title" class="text-lg font-semibold">
                    Provider reconciliation
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Key-wide usage compared with local settlements. Only this
                    action contacts Pixelforge.
                </p>
            </div>
            <Button
                :disabled="processing"
                aria-describedby="provider-refresh-feedback"
                @click="$emit('refresh')"
                ><RefreshCw
                    class="size-4"
                    :class="{
                        'animate-spin motion-reduce:animate-none': processing,
                    }"
                />{{
                    processing ? 'Fetching usage…' : 'Refresh provider usage'
                }}</Button
            >
        </div>
        <InputError
            id="provider-refresh-feedback"
            :message="error"
            role="alert"
        />
        <div
            class="flex flex-wrap items-center gap-3 text-sm"
            aria-live="polite"
        >
            <Badge
                :variant="
                    reconciliation.status === 'MISMATCHED'
                        ? 'destructive'
                        : 'outline'
                "
                >{{ reconciliation.status }}</Badge
            >
            <template v-if="reconciliation.provider_total">
                <span
                    >Provider successes:
                    {{ reconciliation.provider_total.generations }}</span
                >
                <span class="font-mono tabular-nums"
                    >Provider total: ${{
                        reconciliation.provider_total.provider_cost_usd
                    }}</span
                >
                <span class="font-mono tabular-nums"
                    >Local minus provider: ${{
                        reconciliation.total_cost_difference_usd
                    }}</span
                >
            </template>
        </div>
        <p v-if="refreshed" role="status" class="text-sm text-primary">
            Provider usage fetched successfully.
        </p>
        <div class="space-y-1 text-xs text-muted-foreground" aria-live="polite">
            <p>
                Last successful fetch:
                {{
                    formatGenerationDate(
                        reconciliation.last_successful_fetch_at,
                    )
                }}
            </p>
            <p v-if="reconciliation.last_attempt_at">
                Last attempt:
                {{ formatGenerationDate(reconciliation.last_attempt_at) }}
            </p>
            <p v-if="reconciliation.next_refresh_at">
                Next refresh permitted:
                {{ formatGenerationDate(reconciliation.next_refresh_at) }}
            </p>
        </div>
        <Alert
            v-if="reconciliation.is_stale || reconciliation.error_code"
            class="border-amber-500/50"
            ><AlertDescription
                >Provider comparison is unavailable{{
                    reconciliation.is_stale
                        ? '; previous successful data is stale'
                        : ''
                }}.
                {{
                    reconciliation.error_code ??
                    'Refresh to obtain current data.'
                }}</AlertDescription
            ></Alert
        >
        <p class="text-xs leading-relaxed text-muted-foreground">
            The provider report covers the configured environment's API key,
            across all users. Local totals cover stored settlements. Key changes
            or generations created elsewhere can cause discrepancies. Open UTC
            days may change after fetching. Refresh is limited to once per
            minute, or longer when Retry-After requires it; snapshots become
            stale after 10 minutes.
        </p>
        <div
            class="max-w-full overflow-x-auto focus-visible:outline-ring"
            role="region"
            aria-label="Daily reconciliation comparison"
            tabindex="0"
        >
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Daily provider reconciliation; difference is local minus
                    provider
                </caption>
                <thead class="border-b text-xs text-muted-foreground">
                    <tr>
                        <th scope="col" class="py-3 pr-4 text-left">UTC day</th>
                        <th scope="col" class="p-3 text-left">Result</th>
                        <th scope="col" class="p-3 text-right">
                            Local provider cost
                        </th>
                        <th scope="col" class="p-3 text-right">
                            Provider report
                        </th>
                        <th scope="col" class="py-3 pl-3 text-right">
                            Difference
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="day in reconciliation.days" :key="day.date">
                        <th
                            scope="row"
                            class="py-4 pr-4 text-left align-top font-normal whitespace-nowrap"
                        >
                            {{ day.date }}
                        </th>
                        <td class="p-3 align-top">
                            <Badge
                                :variant="
                                    day.status === 'MISMATCHED'
                                        ? 'destructive'
                                        : 'outline'
                                "
                                >{{ day.status }}</Badge
                            >
                            <details
                                v-if="day.provider"
                                class="mt-2 max-w-xs text-xs"
                            >
                                <summary
                                    class="cursor-pointer text-muted-foreground"
                                >
                                    Compare counts
                                </summary>
                                <dl class="mt-2 space-y-1">
                                    <div
                                        v-for="metric in [
                                            'generations',
                                            'input_text_tokens',
                                            'input_image_tokens',
                                            'output_tokens',
                                            'reasoning_tokens',
                                        ] as const"
                                        :key="metric"
                                    >
                                        <dt>
                                            {{ metric.replaceAll('_', ' ') }}
                                        </dt>
                                        <dd class="font-mono">
                                            {{ day.local[metric] }} local /
                                            {{ day.provider[metric] }} provider
                                            · Δ {{ day.differences[metric] }}
                                        </dd>
                                    </div>
                                </dl>
                            </details>
                        </td>
                        <td
                            class="p-3 text-right align-top font-mono whitespace-nowrap tabular-nums"
                        >
                            ${{ day.local.provider_cost_usd }}
                        </td>
                        <td
                            class="p-3 text-right align-top font-mono whitespace-nowrap tabular-nums"
                        >
                            {{
                                day.provider
                                    ? `$${day.provider.provider_cost_usd}`
                                    : 'Not available'
                            }}
                        </td>
                        <td
                            class="py-3 pl-3 text-right align-top font-mono whitespace-nowrap tabular-nums"
                        >
                            {{
                                day.provider
                                    ? `$${day.differences.provider_cost_usd}`
                                    : '—'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
