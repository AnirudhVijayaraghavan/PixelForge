<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { formatGenerationDate } from '@/lib/generations';
import { show } from '@/routes/admin/financials';
import type { FinancialGeneration } from '@/types/financial-report';

defineProps<{ rows: FinancialGeneration[] }>();
</script>
<template>
    <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <caption class="sr-only">
                Generation settlement drill-down, exact USD
            </caption>
            <thead class="bg-muted/50 text-xs text-muted-foreground">
                <tr>
                    <th scope="col" class="p-4 text-left">
                        Generation / provider ID
                    </th>
                    <th scope="col" class="p-4 text-left">
                        User / completion UTC
                    </th>
                    <th scope="col" class="p-4 text-left">Status</th>
                    <th scope="col" class="p-4 text-right">Provider cost</th>
                    <th scope="col" class="p-4 text-right">Customer charge</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="generation in rows" :key="generation.id">
                    <td class="p-4">
                        <Link
                            :href="show(generation.id)"
                            class="font-mono text-xs text-primary underline underline-offset-4"
                            >{{ generation.id }}</Link
                        >
                        <p class="mt-1 font-mono text-xs text-muted-foreground">
                            {{ generation.provider_generation_id }}
                        </p>
                    </td>
                    <td class="p-4">
                        <p>{{ generation.user_name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ formatGenerationDate(generation.completed_at) }}
                        </p>
                    </td>
                    <td class="p-4">
                        <Badge variant="outline">{{ generation.status }}</Badge>
                    </td>
                    <td
                        class="p-4 text-right font-mono whitespace-nowrap tabular-nums"
                    >
                        {{
                            generation.provider_cost_usd === null
                                ? 'Unavailable'
                                : `$${generation.provider_cost_usd}`
                        }}
                    </td>
                    <td
                        class="p-4 text-right font-mono whitespace-nowrap tabular-nums"
                    >
                        {{
                            generation.customer_charge_usd === null
                                ? 'Unavailable'
                                : `$${generation.customer_charge_usd}`
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="!rows.length" class="p-8 text-center text-muted-foreground">
            No completed generations in this period.
        </p>
    </div>
</template>
