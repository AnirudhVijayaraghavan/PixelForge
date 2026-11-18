<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import GenerationComposer from '@/components/generations/GenerationComposer.vue';
import { Button } from '@/components/ui/button';
import { studio } from '@/routes';
import { index } from '@/routes/generations';
import type { GenerationInputs } from '@/types/generation';
defineOptions({
    layout: { breadcrumbs: [{ title: 'Studio', href: studio() }] },
});
defineProps<{ inputs: GenerationInputs; reusing: boolean }>();
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
        <GenerationComposer
            :key="JSON.stringify(inputs)"
            :inputs="inputs"
            :reusing="reusing"
        />
    </div>
</template>
