<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Images, Plus } from '@lucide/vue';
import { ref } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useGenerationPolling } from '@/composables/useGenerationPolling';
import { studio } from '@/routes';
import { index } from '@/routes/generations';
import type { GenerationPage } from '@/types/generation';
defineOptions({
    layout: { breadcrumbs: [{ title: 'My Generations', href: index() }] },
});
const props = defineProps<{ generations: GenerationPage }>();
const { records, error, refresh } = useGenerationPolling(
    () => props.generations.data,
);
const navigating = ref(false);
</script>
<template>
    <div class="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <Head title="My Generations" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="mb-2 text-[11px] font-medium tracking-[0.18em] text-primary uppercase"
                >
                    Your creative archive
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    My Generations
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Every idea, its result, and exactly what it cost.
                </p>
            </div>
            <Button as-child
                ><Link :href="studio()"><Plus /> New generation</Link></Button
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
            v-if="navigating"
            class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
            role="status"
            aria-label="Loading generations"
        >
            <Skeleton
                v-for="position in 6"
                :key="position"
                class="h-96 rounded-xl motion-reduce:animate-none"
            />
        </div>
        <section
            v-else-if="records.length"
            class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
            aria-label="Generation gallery"
        >
            <GenerationCard
                v-for="generation in records"
                :key="generation.id"
                :generation="generation"
            />
        </section>
        <div
            v-else
            class="flex min-h-96 flex-col items-center justify-center rounded-2xl border border-dashed bg-card px-6 py-16 text-center"
        >
            <span
                class="mb-5 flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                ><Images class="size-6"
            /></span>
            <h2 class="text-xl font-semibold">A space for your next idea</h2>
            <p
                class="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
            >
                Your images and exact cost breakdowns will appear here. Start
                with a prompt in the Studio.
            </p>
            <Button class="mt-6" as-child
                ><Link :href="studio()"
                    ><Plus /> Create your first image</Link
                ></Button
            >
        </div>
        <nav
            v-if="generations.total"
            class="flex flex-wrap items-center justify-between gap-3 border-t pt-5"
            aria-label="Generation pages"
        >
            <p class="text-xs text-muted-foreground">
                {{ generations.from }}–{{ generations.to }} of
                {{ generations.total }} generations · Page
                {{ generations.current_page }} of {{ generations.last_page }}
            </p>
            <div class="flex gap-2">
                <Button
                    v-if="generations.current_page > 1"
                    variant="outline"
                    size="sm"
                    as-child
                    ><Link
                        :href="
                            index({
                                query: { page: generations.current_page - 1 },
                            })
                        "
                        preserve-scroll
                        @start="navigating = true"
                        @finish="navigating = false"
                        ><ArrowLeft /> Previous</Link
                    ></Button
                ><Button v-else variant="outline" size="sm" disabled
                    ><ArrowLeft /> Previous</Button
                ><Button
                    v-if="generations.current_page < generations.last_page"
                    variant="outline"
                    size="sm"
                    as-child
                    ><Link
                        :href="
                            index({
                                query: { page: generations.current_page + 1 },
                            })
                        "
                        preserve-scroll
                        @start="navigating = true"
                        @finish="navigating = false"
                        >Next <ArrowRight /></Link></Button
                ><Button v-else variant="outline" size="sm" disabled
                    >Next <ArrowRight
                /></Button>
            </div>
        </nav>
        <p class="text-xs text-muted-foreground">
            Live status refreshes while active requests are visible. Image links
            expire 24 hours after completion.
        </p>
    </div>
</template>
