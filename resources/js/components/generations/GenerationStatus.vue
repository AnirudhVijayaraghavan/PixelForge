<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import type { Generation } from '@/types/generation';
defineProps<{ generation: Generation }>();
</script>
<template>
    <Badge
        variant="outline"
        :class="{
            'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300':
                generation.status === 'succeeded',
            'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300': [
                'failed',
                'rejected',
            ].includes(generation.status),
            'border-primary/30 bg-primary/5 text-primary': generation.active,
        }"
        ><LoaderCircle
            v-if="generation.active"
            class="size-3 motion-safe:animate-spin"
        />{{ generation.status_label }}</Badge
    >
</template>
