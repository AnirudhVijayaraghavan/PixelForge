<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, LoaderCircle, Sparkles } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PriceEstimate from '@/components/generations/PriceEstimate.vue';
import GenerationOptions from '@/components/generations/GenerationOptions.vue';
import ReferenceImageInputs from '@/components/generations/ReferenceImageInputs.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useGenerationComposer } from '@/composables/useGenerationComposer';
import { index } from '@/routes/generations';
import type { GenerationInputs } from '@/types/generation';

const props = defineProps<{ inputs: GenerationInputs; reusing: boolean }>();
const {
    form,
    quote,
    quoteLoading,
    quoteError,
    message,
    uncertain,
    characterCount,
    canSubmit,
    submit,
    refreshQuote,
} = useGenerationComposer(
    props.inputs,
    usePage().props.auth.user.id,
    props.reusing,
);
const examples = [
    'An editorial still life of translucent glass objects, soft lavender light, clean shadows, on a charcoal backdrop.',
    'A quiet coastal house at blue hour, warm interior light, architectural photography, subtle film grain.',
    'A playful paper sculpture of a tiny botanical world, sculptural shapes, natural studio lighting.',
];
</script>

<template>
    <form
        class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
        novalidate
        @submit.prevent="submit"
    >
        <div class="min-w-0 space-y-6">
            <Alert
                v-if="uncertain && !form.processing"
                class="border-amber-500/40"
                role="alert"
            >
                <AlertTitle>Your request may already be saved</AlertTitle>
                <AlertDescription class="space-y-3"
                    ><p>
                        We could not confirm the response. Check My Generations
                        before creating another request. Nothing will be
                        resubmitted automatically.
                    </p>
                    <Link
                        :href="index()"
                        class="font-medium text-primary underline underline-offset-4"
                        >Check My Generations</Link
                    ><Button
                        type="button"
                        variant="outline"
                        class="mt-2 h-auto min-h-9 w-full text-left whitespace-normal"
                        @click="uncertain = false"
                        >I checked my history — allow a new request</Button
                    ></AlertDescription
                >
            </Alert>
            <Alert v-if="message" role="alert"
                ><AlertTitle>Check your request</AlertTitle
                ><AlertDescription>{{ message }}</AlertDescription></Alert
            >
            <fieldset
                :disabled="form.processing"
                class="min-w-0 space-y-6 disabled:opacity-70"
            >
                <legend class="sr-only">Image creation settings</legend>
                <Card class="gap-4 shadow-none">
                    <CardHeader
                        ><CardTitle class="flex items-center gap-2 text-base"
                            ><Sparkles class="size-4 text-primary" /> Describe
                            your image</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <Label for="generation-prompt" class="sr-only"
                            >Image prompt</Label
                        >
                        <textarea
                            id="generation-prompt"
                            v-model="form.prompt"
                            name="prompt"
                            rows="8"
                            required
                            class="min-h-56 w-full resize-y rounded-xl border border-input bg-background/50 p-4 text-base leading-relaxed outline-none placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Describe the scene, mood, lighting, and details you have in mind…"
                            aria-describedby="prompt-help prompt-count prompt-error"
                            :aria-invalid="
                                !!form.errors.prompt || characterCount > 4000
                            "
                        />
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                        >
                            <p id="prompt-help">
                                A little specificity goes a long way.
                            </p>
                            <p
                                id="prompt-count"
                                :class="{
                                    'text-destructive': characterCount > 4000,
                                }"
                            >
                                {{ characterCount.toLocaleString('en-US') }} /
                                4,000 characters
                            </p>
                        </div>
                        <InputError
                            id="prompt-error"
                            :message="
                                form.errors.prompt ||
                                (characterCount > 4000
                                    ? 'Use no more than 4,000 Unicode characters.'
                                    : undefined)
                            "
                        />
                    </CardContent>
                </Card>
                <GenerationOptions
                    v-model:size="form.size"
                    v-model:quality="form.quality"
                    :errors="form.errors"
                />
                <ReferenceImageInputs
                    v-model="form.reference_images"
                    :errors="form.errors"
                />
            </fieldset>
            <div class="space-y-3">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Need a starting point?
                </p>
                <div class="grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="(example, position) in examples"
                        :key="example"
                        type="button"
                        class="rounded-lg border border-dashed p-3 text-left text-xs leading-relaxed text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        :disabled="form.processing"
                        @click="form.prompt = example"
                    >
                        <span class="mb-1 block font-medium text-foreground"
                            >{{
                                [
                                    'Studio still life',
                                    'Architectural mood',
                                    'Paper worlds',
                                ][position]
                            }}
                            <ArrowUpRight class="ml-1 inline size-3" /></span
                        >{{ example }}
                    </button>
                </div>
            </div>
        </div>
        <aside class="min-w-0 space-y-4 xl:sticky xl:top-6 xl:self-start">
            <PriceEstimate
                :quote="quote"
                :loading="quoteLoading"
                :error="quoteError"
                @retry="refreshQuote"
            />
            <Button
                type="submit"
                size="lg"
                class="h-12 w-full"
                :disabled="!canSubmit"
                ><LoaderCircle
                    v-if="form.processing"
                    class="motion-safe:animate-spin"
                /><Sparkles v-else />{{
                    form.processing ? 'Saving your request…' : 'Generate image'
                }}</Button
            >
            <p
                class="text-center text-xs leading-relaxed text-muted-foreground"
            >
                Your request runs in the background.<br />You can safely leave
                after it is saved.
            </p>
        </aside>
    </form>
</template>
