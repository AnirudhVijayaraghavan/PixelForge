<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import type { GenerationSize, GenerationQuality } from '@/types/generation';
const sizeValue = defineModel<GenerationSize>('size', { required: true });
const qualityValue = defineModel<GenerationQuality>('quality', {
    required: true,
});
defineProps<{ errors: { size?: string; quality?: string } }>();
const sizes = [
    { value: '1024x1024', title: 'Square', ratio: 'aspect-square' },
    { value: '1536x1024', title: 'Landscape', ratio: 'aspect-[3/2]' },
    { value: '1024x1536', title: 'Portrait', ratio: 'aspect-[2/3]' },
] as const;
const qualities = [
    { value: 'low', title: 'Low', description: 'Quick explorations' },
    { value: 'medium', title: 'Medium', description: 'Everyday balance' },
    { value: 'high', title: 'High', description: 'More refined detail' },
] as const;
</script>
<template>
    <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
        <fieldset class="min-w-0">
            <legend class="mb-3 text-sm font-medium">Image size</legend>
            <div class="grid grid-cols-3 gap-2">
                <label
                    v-for="option in sizes"
                    :key="option.value"
                    class="relative cursor-pointer"
                    ><input
                        v-model="sizeValue"
                        type="radio"
                        name="size"
                        :value="option.value"
                        class="peer sr-only"
                    /><span
                        class="flex h-full flex-col items-center gap-2 rounded-xl border bg-card p-3 text-center transition-colors peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-ring"
                        ><span class="flex h-7 items-center"
                            ><span
                                :class="[
                                    'block h-5 rounded-sm border-2 border-current',
                                    option.ratio,
                                ]" /></span
                        ><span class="text-xs font-medium">{{
                            option.title
                        }}</span
                        ><span class="text-[10px] text-muted-foreground">{{
                            option.value.replace('x', ' × ')
                        }}</span></span
                    ></label
                >
            </div>
            <InputError :message="errors.size" />
        </fieldset>
        <fieldset class="min-w-0">
            <legend class="mb-3 text-sm font-medium">Quality</legend>
            <div class="grid grid-cols-3 gap-2">
                <label
                    v-for="option in qualities"
                    :key="option.value"
                    class="relative cursor-pointer"
                    ><input
                        v-model="qualityValue"
                        type="radio"
                        name="quality"
                        :value="option.value"
                        class="peer sr-only"
                    /><span
                        class="flex h-full flex-col gap-2 rounded-xl border bg-card px-2 py-4 text-center transition-colors peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-ring"
                        ><span class="text-sm font-medium">{{
                            option.title
                        }}</span
                        ><span
                            class="text-[11px] leading-relaxed text-muted-foreground"
                            >{{ option.description }}</span
                        ></span
                    ></label
                >
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                Higher quality uses more tokens and increases the price.
            </p>
            <InputError :message="errors.quality" />
        </fieldset>
    </div>
</template>
