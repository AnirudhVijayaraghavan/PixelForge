<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, RotateCcw } from '@lucide/vue';
import GenerationImage from '@/components/generations/GenerationImage.vue';
import GenerationStatus from '@/components/generations/GenerationStatus.vue';
import { Button } from '@/components/ui/button';
import { formatGenerationDate } from '@/lib/generations';
import { studio } from '@/routes';
import { show } from '@/routes/generations';
import type { Generation } from '@/types/generation';
defineProps<{ generation: Generation }>();
</script>
<template>
    <article
        class="group min-w-0 overflow-hidden rounded-xl border bg-card transition-colors hover:border-primary/30"
    >
        <Link
            :href="show(generation.id)"
            class="block focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring"
            :aria-label="`View generation: ${generation.prompt_excerpt}`"
            ><GenerationImage
                :generation="generation"
                class="aspect-[4/3] w-full border-b [&_img]:object-cover"
        /></Link>
        <div class="space-y-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <GenerationStatus :generation="generation" /><span
                    class="text-[11px] text-muted-foreground"
                    >{{ generation.size.replace('x', ' × ') }} ·
                    {{ generation.quality }}</span
                >
            </div>
            <h2 class="line-clamp-2 min-h-10 text-sm leading-5 font-medium">
                <Link
                    :href="show(generation.id)"
                    class="rounded-sm hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                    >{{ generation.prompt_excerpt }}</Link
                >
            </h2>
            <p
                v-if="generation.status !== 'succeeded'"
                class="text-xs leading-relaxed text-muted-foreground"
            >
                {{ generation.status_message }}
            </p>
            <dl class="space-y-2 text-xs">
                <div class="flex flex-wrap justify-between gap-1">
                    <dt class="text-muted-foreground">Original estimate</dt>
                    <dd class="font-mono tabular-nums">
                        ${{ generation.estimated_customer_charge_usd }}
                    </dd>
                </div>
                <div class="flex flex-wrap justify-between gap-1 border-t pt-2">
                    <dt class="text-muted-foreground">
                        {{
                            generation.billing_state === 'free'
                                ? 'Charged'
                                : 'Actual charge'
                        }}
                    </dt>
                    <dd class="font-mono font-medium tabular-nums">
                        {{
                            generation.actual_customer_charge_usd !== null
                                ? `$${generation.actual_customer_charge_usd}`
                                : generation.billing_state === 'unavailable'
                                  ? 'Needs verification'
                                  : 'Pending'
                        }}
                    </dd>
                </div>
            </dl>
            <time
                :datetime="generation.created_at"
                class="block text-[10px] text-muted-foreground"
                >{{ formatGenerationDate(generation.created_at) }}</time
            >
            <div class="flex justify-between gap-2">
                <Button variant="ghost" size="sm" as-child
                    ><Link :href="studio({ query: { reuse: generation.id } })"
                        ><RotateCcw class="size-3.5" /> Reuse prompt</Link
                    ></Button
                ><Button variant="outline" size="sm" as-child
                    ><Link :href="show(generation.id)"
                        >Details <ArrowUpRight class="size-3.5" /></Link
                ></Button>
            </div>
        </div>
    </article>
</template>
