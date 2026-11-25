<script setup lang="ts">
import type { FinancialRow } from '@/types/financial-report';

defineProps<{
    rows: FinancialRow[];
    grouping: 'date' | 'user' | 'date-user';
    caption: string;
    showTokens?: boolean;
}>();
</script>

<template>
    <div
        class="max-w-full overflow-x-auto rounded-xl border focus-visible:outline-ring"
        role="region"
        aria-label="Financial totals"
        tabindex="0"
    >
        <table class="w-full text-sm">
            <caption class="sr-only">
                {{
                    caption
                }}. Amounts are exact USD.
            </caption>
            <thead class="bg-muted/50 text-xs text-muted-foreground">
                <tr>
                    <th
                        v-if="grouping !== 'user'"
                        scope="col"
                        class="px-4 py-3 text-left"
                    >
                        UTC completion date
                    </th>
                    <th
                        v-if="grouping !== 'date'"
                        scope="col"
                        class="px-4 py-3 text-left"
                    >
                        User
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">Succeeded</th>
                    <th scope="col" class="px-4 py-3 text-right">
                        Customer charges
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">
                        Provider costs
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">
                        Gross profit
                    </th>
                    <template v-if="showTokens">
                        <th scope="col" class="px-4 py-3 text-right">
                            Text tokens
                        </th>
                        <th scope="col" class="px-4 py-3 text-right">
                            Image tokens
                        </th>
                        <th scope="col" class="px-4 py-3 text-right">
                            Output tokens
                        </th>
                        <th scope="col" class="px-4 py-3 text-right">
                            Reasoning tokens
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr
                    v-for="row in rows"
                    :key="`${row.date ?? ''}:${row.user_id ?? ''}`"
                    class="hover:bg-muted/20"
                >
                    <th
                        v-if="grouping !== 'user'"
                        scope="row"
                        class="px-4 py-3 text-left font-normal whitespace-nowrap"
                    >
                        {{ row.date }}
                    </th>
                    <th
                        v-if="grouping !== 'date'"
                        scope="row"
                        class="px-4 py-3 text-left font-normal"
                    >
                        <div>{{ row.name }}</div>
                        <div
                            v-if="row.email"
                            class="text-xs text-muted-foreground"
                        >
                            {{ row.email }}
                        </div>
                    </th>
                    <td class="px-4 py-3 text-right font-mono tabular-nums">
                        {{ row.generations }}
                    </td>
                    <td
                        class="px-4 py-3 text-right font-mono whitespace-nowrap tabular-nums"
                    >
                        ${{ row.customer_charge_usd }}
                    </td>
                    <td
                        class="px-4 py-3 text-right font-mono whitespace-nowrap tabular-nums"
                    >
                        ${{ row.provider_cost_usd }}
                    </td>
                    <td
                        class="px-4 py-3 text-right font-mono whitespace-nowrap tabular-nums"
                    >
                        ${{ row.gross_profit_usd }}
                    </td>
                    <template v-if="showTokens">
                        <td class="px-4 py-3 text-right font-mono tabular-nums">
                            {{ row.input_text_tokens }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums">
                            {{ row.input_image_tokens }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums">
                            {{ row.output_tokens }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums">
                            {{ row.reasoning_tokens }}
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
        <p v-if="!rows.length" class="p-8 text-center text-muted-foreground">
            No settlements in this date range.
        </p>
    </div>
</template>
