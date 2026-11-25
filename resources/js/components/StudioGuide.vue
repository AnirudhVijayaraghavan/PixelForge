<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { computed, nextTick, onMounted, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
const props = defineProps<{ userId: number; firstUse: boolean }>();
const key = 'pixelforge:studio-guide:v1:' + props.userId;
const offer = ref(false);
const dialog = ref<HTMLDialogElement | null>(null);
const heading = ref<HTMLElement | null>(null);
const replay = ref<HTMLButtonElement | null>(null);
const step = ref(0);
const steps = [
    {
        target: 'generation-prompt',
        title: 'Write your prompt',
        body: 'Describe a subject, a setting and a mood in ordinary language. The composer accepts up to 4,000 Unicode characters.',
    },
    {
        target: 'studio-settings',
        title: 'Choose the image settings',
        body: 'Choose a size and quality. Add up to four optional HTTP or HTTPS reference URLs. Each reference adds billable input tokens.',
    },
    {
        target: 'studio-price',
        title: 'Check the price and credits',
        body: 'The estimate is the expected charge. Available credits must cover the maximum reservation at the quoted rates. Success captures the actual charge and releases unused credits. Higher accepted rates can exceptionally take the charge above the original hold.',
    },
    {
        target: 'studio-lifecycle',
        title: 'Follow a generation',
        body: 'Queued means waiting. Processing means the image is being created. Success records the final charge. A confirmed failure is free. If delivery is delayed, check history while Studio verifies the existing request.',
    },
    {
        target: 'studio-history',
        title: 'Explore history and costs',
        body: 'Open My Generations and select an image to review its original prompt, confirmed tokens, rates and exact final charge in the Cost Inspector.',
    },
] as const;
const current = computed(() => steps[step.value]);
let marked: HTMLElement | null = null;
let returnY = 0;
function remember(value: string) {
    offer.value = false;
    try {
        localStorage.setItem(key, value);
    } catch {
        /* Guide remains usable without persistent storage. */
    }
}
function skipInvitation() {
    remember('skipped');
    void nextTick(() => replay.value?.focus({ preventScroll: true }));
}
function clearMark() {
    marked?.removeAttribute('data-guide-active');
    marked = null;
}
async function reveal() {
    await nextTick();
    if (!dialog.value?.open) return;
    clearMark();
    marked = document.getElementById(current.value.target);
    if (marked?.getClientRects().length) {
        marked.dataset.guideActive = '';
        marked.scrollIntoView({ block: 'start', behavior: 'instant' });
    }
    heading.value?.focus({ preventScroll: true });
}
function open() {
    if (!dialog.value) return;
    returnY = window.scrollY;
    step.value = 0;
    offer.value = false;
    dialog.value.showModal();
    void reveal();
}
function move(delta: number) {
    step.value += delta;
    void reveal();
}
function dismiss(result: 'skipped' | 'finished') {
    remember(result);
    clearMark();
    dialog.value?.close();
    replay.value?.focus({ preventScroll: true });
    window.scrollTo({ top: returnY, behavior: 'instant' });
}
onMounted(() => {
    try {
        offer.value = props.firstUse && !localStorage.getItem(key);
        if (offer.value) localStorage.setItem(key, 'offered');
    } catch {
        offer.value = props.firstUse;
    }
});
onBeforeUnmount(() => {
    clearMark();
    dialog.value?.close();
});
</script>
<template>
    <section aria-label="Studio help" class="space-y-2">
        <div
            v-if="offer"
            class="flex flex-wrap items-center gap-3 rounded-xl border bg-muted/40 p-4"
        >
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold">Your first generation</h2>
                <p class="text-sm text-muted-foreground">
                    Take a five-step tour of prompts, credits and results.
                </p>
            </div>
            <Button type="button" @click="open">Take the tour</Button
            ><Button variant="ghost" type="button" @click="skipInvitation"
                >Skip tour</Button
            >
        </div>
        <button
            ref="replay"
            type="button"
            class="inline-flex min-h-10 items-center gap-2 rounded-md px-2 text-sm font-medium hover:underline focus-visible:outline-2"
            @click="open"
        >
            <CircleHelp :size="16" /> Help · Studio tour
        </button>
        <dialog
            ref="dialog"
            aria-labelledby="guide-title"
            aria-describedby="guide-text"
            class="guide rounded-xl border bg-card p-5 text-card-foreground shadow-xl"
            @cancel.prevent="dismiss('skipped')"
        >
            <p class="text-xs font-medium text-muted-foreground" role="status">
                Studio tour · Step {{ step + 1 }} of {{ steps.length }}
            </p>
            <h2
                ref="heading"
                id="guide-title"
                tabindex="-1"
                class="mt-2 text-xl font-semibold"
            >
                {{ current.title }}
            </h2>
            <p id="guide-text" class="mt-2 text-sm leading-relaxed">
                {{ current.body }}
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <Button variant="ghost" @click="dismiss('skipped')"
                    >Skip tour</Button
                ><Button
                    variant="outline"
                    class="ml-auto"
                    :disabled="step === 0"
                    @click="move(-1)"
                    >Back</Button
                ><Button v-if="step < steps.length - 1" @click="move(1)"
                    >Next</Button
                ><Button v-else @click="dismiss('finished')">Finish</Button>
            </div>
        </dialog>
    </section>
</template>
<style scoped>
.guide {
    position: fixed;
    inset: auto 1rem max(1rem, env(safe-area-inset-bottom)) auto;
    width: min(28rem, calc(100% - 2rem));
    max-height: 70dvh;
    overflow: auto;
}
.guide::backdrop {
    background: rgb(12 20 18/0.23);
}
:global([data-guide-active]) {
    outline: 3px solid var(--primary);
    outline-offset: 3px;
    scroll-margin-top: 1rem;
}
</style>
