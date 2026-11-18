<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <rect x="3" y="3" width="11" height="11" rx="2" fill="currentColor" />
        <rect
            x="18"
            y="3"
            width="11"
            height="11"
            rx="2"
            fill="currentColor"
            opacity="0.55"
        />
        <rect
            x="3"
            y="18"
            width="11"
            height="11"
            rx="2"
            fill="currentColor"
            opacity="0.55"
        />
        <path
            d="m23.5 16 2.2 5.3L31 23.5l-5.3 2.2-2.2 5.3-2.2-5.3-5.3-2.2 5.3-2.2z"
            fill="currentColor"
        />
    </svg>
</template>
