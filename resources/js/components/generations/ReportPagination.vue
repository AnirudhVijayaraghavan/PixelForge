<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    page: { current_page: number; last_page: number; total: number };
    label: string;
    loading: boolean;
}>();
defineEmits<{ change: [page: number] }>();
</script>

<template>
    <nav
        class="mt-4 flex flex-wrap items-center justify-between gap-3"
        :aria-label="`${label} pagination`"
    >
        <p class="text-xs text-muted-foreground">
            Page {{ page.current_page }} of {{ page.last_page }} ·
            {{ page.total }} records
        </p>
        <div class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="loading || page.current_page <= 1"
                :aria-label="`Previous ${label} page`"
                @click="$emit('change', page.current_page - 1)"
                >Previous</Button
            >
            <Button
                variant="outline"
                size="sm"
                :disabled="loading || page.current_page >= page.last_page"
                :aria-label="`Next ${label} page`"
                @click="$emit('change', page.current_page + 1)"
                >Next</Button
            >
        </div>
    </nav>
</template>
