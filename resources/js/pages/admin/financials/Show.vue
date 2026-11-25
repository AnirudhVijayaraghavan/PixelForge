<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GenerationCostBreakdown from '@/components/generations/GenerationCostBreakdown.vue';
import { Button } from '@/components/ui/button';
import { formatGenerationDate } from '@/lib/generations';
import { index } from '@/routes/admin/financials';
import type { GenerationDetail } from '@/types/generation';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Financial reports', href: index() }] },
});
defineProps<{
    generation: GenerationDetail;
    provider_generation_id: string | null;
    user_name: string;
}>();
</script>

<template>
    <div class="mx-auto w-full max-w-5xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <Head title="Generation financial detail" />
        <header class="space-y-3">
            <Button as-child variant="outline" size="sm"
                ><Link :href="index()">Back to reports</Link></Button
            >
            <h1 class="text-2xl font-semibold tracking-tight">
                Generation financial detail
            </h1>
            <p class="text-sm text-muted-foreground">
                Read-only admin inspection · {{ user_name }} ·
                {{ generation.status_label }}
            </p>
        </header>
        <dl
            class="grid gap-4 rounded-xl border bg-card p-5 text-sm sm:grid-cols-2"
        >
            <div>
                <dt class="text-muted-foreground">Local generation</dt>
                <dd class="mt-1 font-mono text-xs break-all">
                    {{ generation.id }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Provider generation</dt>
                <dd class="mt-1 font-mono text-xs break-all">
                    {{ provider_generation_id ?? 'Not accepted' }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Completion</dt>
                <dd class="mt-1">
                    {{ formatGenerationDate(generation.completed_at) }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Settings</dt>
                <dd class="mt-1">
                    {{ generation.size }} · {{ generation.quality }}
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-muted-foreground">Prompt</dt>
                <dd class="mt-1 break-words whitespace-pre-wrap">
                    {{ generation.prompt }}
                </dd>
            </div>
        </dl>
        <GenerationCostBreakdown :billing="generation.billing" />
    </div>
</template>
