<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useNow } from '@vueuse/core';
import { ArrowLeft, ExternalLink, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import GenerationCostBreakdown from '@/components/generations/GenerationCostBreakdown.vue';
import GenerationImage from '@/components/generations/GenerationImage.vue';
import GenerationStatus from '@/components/generations/GenerationStatus.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useGenerationPolling } from '@/composables/useGenerationPolling';
import { formatGenerationDate } from '@/lib/generations';
import { studio } from '@/routes';
import { index } from '@/routes/generations';
import type { GenerationDetail } from '@/types/generation';
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Generations', href: index() },
            { title: 'Generation detail' },
        ],
    },
});
const props = defineProps<{ generation: GenerationDetail }>();
const { records, error, refresh } = useGenerationPolling(
    () => [props.generation],
    true,
);
const current = computed(() => records.value[0] ?? props.generation);
const now = useNow({ interval: 1000 });
const openImage = computed(() =>
    current.value.image_url &&
    (!current.value.image_url_expires_at ||
        new Date(current.value.image_url_expires_at).getTime() >
            now.value.getTime())
        ? current.value.image_url
        : null,
);
</script>
<template>
    <div class="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <Head title="Generation detail" />
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <Link
                    :href="index()"
                    class="mb-3 inline-flex items-center gap-2 rounded-sm text-xs text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    ><ArrowLeft class="size-3.5" /> My Generations</Link
                >
                <h1 class="text-2xl font-semibold tracking-tight">
                    Generation detail
                </h1>
            </div>
            <Button variant="outline" as-child
                ><Link :href="studio({ query: { reuse: current.id } })"
                    ><RotateCcw /> Reuse prompt</Link
                ></Button
            >
        </header>
        <Alert v-if="error" role="status"
            ><AlertDescription
                class="flex flex-wrap items-center justify-between gap-2"
                ><span>{{ error }}</span
                ><Button variant="outline" size="sm" @click="refresh"
                    >Refresh status</Button
                ></AlertDescription
            ></Alert
        >
        <div
            class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]"
        >
            <div class="min-w-0 space-y-6">
                <div class="overflow-hidden rounded-xl border bg-card">
                    <GenerationImage
                        :generation="current"
                        class="aspect-square w-full"
                    />
                    <div class="space-y-3 border-t p-4">
                        <div aria-live="polite" class="space-y-2">
                            <p
                                v-if="current.credit_message"
                                class="text-sm"
                                role="status"
                            >
                                {{ current.credit_message }}
                            </p>
                            <GenerationStatus :generation="current" />
                            <p
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ current.status_message }}
                            </p>
                        </div>
                        <div
                            v-if="current.image_url_expires_at"
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="text-xs text-muted-foreground">
                                Image link expires
                                {{
                                    formatGenerationDate(
                                        current.image_url_expires_at,
                                    )
                                }}
                            </p>
                            <Button
                                v-if="openImage"
                                variant="outline"
                                size="sm"
                                as-child
                                ><a
                                    :href="openImage"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    referrerpolicy="no-referrer"
                                    >Open image <ExternalLink /></a
                            ></Button>
                        </div>
                    </div>
                </div>
                <Card class="gap-4 shadow-none"
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >The original idea</CardTitle
                        ></CardHeader
                    ><CardContent class="space-y-5"
                        ><p
                            class="text-sm leading-relaxed wrap-anywhere whitespace-pre-wrap"
                        >
                            {{ current.prompt }}
                        </p>
                        <dl class="grid gap-4 text-xs sm:grid-cols-2">
                            <div>
                                <dt class="mb-1 text-muted-foreground">
                                    Image size
                                </dt>
                                <dd>{{ current.size.replace('x', ' × ') }}</dd>
                            </div>
                            <div>
                                <dt class="mb-1 text-muted-foreground">
                                    Quality
                                </dt>
                                <dd class="capitalize">
                                    {{ current.quality }}
                                </dd>
                            </div>
                            <div>
                                <dt class="mb-1 text-muted-foreground">
                                    Created
                                </dt>
                                <dd>
                                    {{
                                        formatGenerationDate(current.created_at)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="mb-1 text-muted-foreground">
                                    Accepted by provider
                                </dt>
                                <dd>
                                    {{
                                        formatGenerationDate(
                                            current.provider_created_at,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="mb-1 text-muted-foreground">
                                    Processing started
                                </dt>
                                <dd>
                                    {{
                                        formatGenerationDate(current.started_at)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="mb-1 text-muted-foreground">
                                    Completed
                                </dt>
                                <dd>
                                    {{
                                        formatGenerationDate(
                                            current.completed_at,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            v-if="current.reference_images.length"
                            class="space-y-2 border-t pt-4"
                        >
                            <h2 class="text-xs font-medium">Reference URLs</h2>
                            <a
                                v-for="(
                                    url, position
                                ) in current.reference_images"
                                :key="position"
                                :href="url"
                                target="_blank"
                                rel="noopener noreferrer"
                                referrerpolicy="no-referrer"
                                class="block rounded-sm text-xs wrap-anywhere text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                                >{{ url }}</a
                            >
                        </div></CardContent
                    ></Card
                >
            </div>
            <GenerationCostBreakdown :billing="current.billing" class="h-fit" />
        </div>
    </div>
</template>
