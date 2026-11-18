<script setup lang="ts">
import { useNow } from '@vueuse/core';
import { ImageOff, ScanLine } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { Generation } from '@/types/generation';
const props = defineProps<{ generation: Generation }>();
const failed = ref(false);
const loaded = ref(false);
const now = useNow({ interval: 1000 });
const expired = computed(
    () =>
        props.generation.image_state === 'expired' ||
        (props.generation.image_url_expires_at !== null &&
            new Date(props.generation.image_url_expires_at).getTime() <=
                now.value.getTime()),
);
const available = computed(
    () => !!props.generation.image_url && !expired.value && !failed.value,
);
watch(
    () => props.generation.image_url,
    () => {
        failed.value = false;
        loaded.value = false;
    },
);
</script>
<template>
    <div
        class="relative flex min-h-48 items-center justify-center overflow-hidden bg-muted/40"
    >
        <template v-if="available"
            ><Skeleton
                v-if="!loaded"
                class="absolute inset-0 rounded-none motion-reduce:animate-none" /><img
                :src="generation.image_url ?? undefined"
                :alt="`Generated image: ${generation.prompt_excerpt}`"
                class="size-full object-contain"
                loading="lazy"
                decoding="async"
                referrerpolicy="no-referrer"
                @load="loaded = true"
                @error="failed = true"
        /></template>
        <div
            v-else
            class="flex max-w-xs flex-col items-center gap-3 px-6 py-12 text-center text-muted-foreground"
        >
            <ScanLine
                v-if="generation.active"
                class="size-8 text-primary/60"
            /><ImageOff v-else class="size-8 opacity-60" />
            <p class="text-sm">
                {{
                    expired
                        ? 'This image link has expired'
                        : generation.status === 'succeeded'
                          ? 'Image preview unavailable'
                          : generation.active
                            ? 'Your image will appear here'
                            : 'No image was created'
                }}
            </p>
            <p v-if="expired" class="text-xs leading-relaxed">
                Result links last 24 hours. Your prompt and cost details remain
                available.
            </p>
        </div>
    </div>
</template>
