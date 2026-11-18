<script setup lang="ts">
import { ReceiptText, ShieldCheck, TriangleAlert } from '@lucide/vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { signedDifference } from '@/lib/generations';
import type { GenerationBilling } from '@/types/generation';
defineProps<{ billing: GenerationBilling }>();
</script>
<template>
    <Card class="min-w-0 gap-5 shadow-none">
        <CardHeader
            ><CardTitle
                class="flex items-center justify-between gap-3 text-base"
                ><span class="flex items-center gap-2"
                    ><ReceiptText class="size-4 text-primary" /> Cost
                    Inspector</span
                ><span class="text-xs font-normal text-muted-foreground"
                    >Exact USD</span
                ></CardTitle
            ></CardHeader
        >
        <CardContent class="min-w-0 space-y-6">
            <Alert
                v-if="billing.state === 'unavailable'"
                class="border-amber-500/40"
                ><TriangleAlert /><AlertTitle
                    >Billing needs verification</AlertTitle
                ><AlertDescription
                    >Success was recorded, but a complete, consistent financial
                    breakdown is unavailable. No zero charge is
                    assumed.</AlertDescription
                ></Alert
            >
            <div
                v-else-if="billing.state === 'free'"
                class="rounded-xl bg-muted/40 p-5"
            >
                <div class="mb-2 flex items-center gap-2 text-sm font-medium">
                    <ShieldCheck class="size-4 text-primary" /> No charge
                </div>
                <p class="font-mono text-2xl tabular-nums">$0.000000000</p>
                <p class="mt-2 text-sm text-muted-foreground">
                    This generation was not charged. No successful usage or
                    settlement has been recorded.
                </p>
            </div>
            <div
                v-else-if="billing.state === 'pending'"
                class="rounded-xl bg-muted/40 p-5"
            >
                <p class="mb-2 text-sm font-medium">
                    Final usage and charge are pending
                </p>
                <p class="font-mono text-2xl tabular-nums">
                    ${{ billing.estimated_customer_charge_usd }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    Original estimate only. Final billing will use confirmed
                    usage and the rates accepted by Pixelforge.
                </p>
            </div>
            <template v-if="billing.state === 'settled'">
                <div
                    class="overflow-x-auto rounded-lg border"
                    tabindex="0"
                    aria-label="Token cost breakdown, scroll horizontally on small screens"
                >
                    <table class="w-full min-w-lg text-sm">
                        <caption class="sr-only">
                            Confirmed token usage and accepted rates. All
                            amounts are exact decimal USD.
                        </caption>
                        <thead
                            class="border-b bg-muted/40 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left font-medium"
                                >
                                    Token category
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-right font-medium"
                                >
                                    Tokens
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-right font-medium"
                                >
                                    Rate / 1M
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-right font-medium"
                                >
                                    Cost
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in billing.rows" :key="row.category">
                                <th
                                    scope="row"
                                    class="px-4 py-3 text-left font-normal"
                                >
                                    {{ row.label }}
                                </th>
                                <td
                                    class="px-4 py-3 text-right font-mono tabular-nums"
                                >
                                    {{ row.tokens.toLocaleString('en-US') }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono tabular-nums"
                                >
                                    ${{ row.rate_usd }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono tabular-nums"
                                >
                                    ${{ row.cost_usd }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t bg-muted/20">
                            <tr>
                                <th
                                    scope="row"
                                    class="px-4 py-3 text-left font-medium"
                                >
                                    Total tokens
                                </th>
                                <td
                                    class="px-4 py-3 text-right font-mono tabular-nums"
                                >
                                    {{
                                        billing.total_tokens?.toLocaleString(
                                            'en-US',
                                        )
                                    }}
                                </td>
                                <td colspan="2" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2">
                        <dt class="text-muted-foreground">
                            Total provider cost
                        </dt>
                        <dd class="font-mono tabular-nums">
                            ${{ billing.provider_cost_usd }}
                        </dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2">
                        <dt class="text-muted-foreground">
                            Platform markup · {{ billing.markup_percentage }}
                        </dt>
                        <dd class="font-mono tabular-nums">
                            ${{ billing.markup_usd }}
                        </dd>
                    </div>
                    <div
                        class="flex flex-wrap justify-between gap-2 border-y py-4 text-base font-semibold"
                    >
                        <dt>Exact customer charge</dt>
                        <dd class="font-mono text-primary tabular-nums">
                            ${{ billing.customer_charge_usd }}
                        </dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2">
                        <dt class="text-muted-foreground">
                            Original estimated charge
                        </dt>
                        <dd class="font-mono tabular-nums">
                            ${{ billing.estimated_customer_charge_usd }}
                        </dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2">
                        <dt class="text-muted-foreground">Actual − estimate</dt>
                        <dd class="font-mono tabular-nums">
                            {{
                                billing.difference_usd !== null
                                    ? signedDifference(billing.difference_usd)
                                    : 'Unavailable'
                            }}
                        </dd>
                    </div>
                </dl>
                <div
                    class="space-y-2 text-xs leading-relaxed text-muted-foreground"
                >
                    <p>{{ billing.rate_provenance }}</p>
                    <p>
                        Provider cost is calculated exactly from confirmed
                        usage. The 25% markup applies once to the total, rounded
                        half-up to nine decimal places only when necessary. No
                        intermediate rounding to cents.
                    </p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
