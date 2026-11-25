<script setup lang="ts">
import { Info, RefreshCw, ShieldCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { GenerationQuote } from '@/types/generation';

defineProps<{
    quote: GenerationQuote | null;
    loading: boolean;
    error: string;
}>();
defineEmits<{ retry: [] }>();
</script>

<template>
    <Card class="gap-5 border-primary/20 bg-card shadow-none">
        <CardHeader
            ><CardTitle class="flex items-center justify-between text-base"
                >Your estimate
                <span class="text-xs font-normal text-muted-foreground"
                    >USD</span
                ></CardTitle
            ></CardHeader
        >
        <CardContent class="space-y-5">
            <div aria-live="polite" :aria-busy="loading">
                <template v-if="loading"
                    ><Skeleton
                        class="mb-2 h-10 w-48 motion-reduce:animate-none"
                    />
                    <p class="text-sm text-muted-foreground">
                        Checking current pricing…
                    </p></template
                >
                <template v-else-if="quote"
                    ><p
                        class="font-mono text-3xl font-medium tracking-tight tabular-nums"
                    >
                        ${{ quote.estimate.customer_charge_usd }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Estimated customer charge
                    </p></template
                >
                <p v-else class="text-sm leading-relaxed text-muted-foreground">
                    {{
                        error ||
                        'Complete your prompt and settings to see a current, server-calculated estimate.'
                    }}
                </p>
            </div>
            <dl v-if="quote && !loading" class="space-y-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-muted-foreground">Provider estimate</dt>
                    <dd class="font-mono tabular-nums">
                        ${{ quote.estimate.provider_cost_usd }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-muted-foreground">Platform markup</dt>
                    <dd>25%</dd>
                </div>
                <div class="border-t pt-3">
                    <dt class="mb-2 text-muted-foreground">
                        Expected range at these rates
                    </dt>
                    <dd
                        class="flex flex-wrap gap-x-2 font-mono text-xs tabular-nums"
                    >
                        <span>${{ quote.minimum.customer_charge_usd }}</span
                        ><span>–</span
                        ><span>${{ quote.maximum.customer_charge_usd }}</span>
                    </dd>
                </div>
                <div>
                    <dt>Maximum credit reservation</dt>
                    <dd class="font-mono">
                        ${{ quote.maximum.customer_charge_usd }}
                    </dd>
                </div>
                <div>
                    <dt>Available credits</dt>
                    <dd class="font-mono">${{ quote.available_usd }}</dd>
                </div>
            </dl>
            <p v-if="quote && !quote.affordable" role="status">
                Add simulated credits to cover this reservation.
            </p>
            <Button
                v-if="error"
                type="button"
                variant="outline"
                class="w-full"
                @click="$emit('retry')"
                ><RefreshCw /> Check pricing again</Button
            >
            <div
                class="flex gap-2 rounded-lg bg-muted/50 p-3 text-xs leading-relaxed text-muted-foreground"
            >
                <Info class="mt-0.5 size-4 shrink-0" />
                <p>
                    Final cost depends on actual token usage. Pricing can change
                    before provider acceptance; this range is an estimate, not a
                    guaranteed maximum.
                </p>
            </div>
            <div class="flex gap-2 text-xs text-muted-foreground">
                <ShieldCheck class="size-4 shrink-0 text-primary" />
                <p>
                    Only completed images are charged. Failed generations cost
                    $0.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
