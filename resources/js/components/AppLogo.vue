<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = 'Pixelforge Studio';
</script>

<template>
    <div class="flex min-w-0 flex-1 items-center gap-2">
        <div
            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
        >
            <AppLogoIcon class="size-5" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">{{
                name
            }}</span>
        </div>
    </div>
</template>
