<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import GenerationComposer from '@/components/generations/GenerationComposer.vue';
import { Button } from '@/components/ui/button';
import { studio } from '@/routes';
import { index } from '@/routes/generations';
import { index as walletPage } from '@/routes/wallet';
import type { WalletBalances, GenerationInputs } from '@/types/generation';
defineOptions({
    layout: { breadcrumbs: [{ title: 'Studio', href: studio() }] },
});
defineProps<{
    inputs: GenerationInputs;
    reusing: boolean;
    wallet: WalletBalances;
}>();
</script>
<template>
    <div class="mx-auto w-full max-w-7xl min-w-0 space-y-8 p-4 sm:p-6 lg:p-8">
        <Head title="Studio" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="mb-2 text-[11px] font-medium tracking-[0.18em] text-primary uppercase"
                >
                    Pixelforge Studio
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Create with intention.
                </h1>
                <p
                    class="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground"
                >
                    Your ideas, made visible. Know the estimate before you begin
                    and the exact cost when it is complete.
                </p>
            </div>
            <Button variant="outline" as-child
                ><Link :href="index()">My Generations <ArrowUpRight /></Link
            ></Button>
        </header>
        <p
            v-if="reusing"
            class="rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm"
        >
            Your previous inputs are ready to edit. Reusing a prompt does not
            create or charge a generation.
        </p>
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4"
        >
            <p>
                Available credits
                <strong class="font-mono">${{ wallet.available }}</strong>
            </p>
            <Button as-child variant="outline"
                ><Link :href="walletPage()">Buy simulated credits</Link></Button
            >
        </div>
        <GenerationComposer
            :key="JSON.stringify(inputs)"
            :inputs="inputs"
            :reusing="reusing"
        />
    </div>
</template>
