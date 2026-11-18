<script setup lang="ts">
defineProps<{ showLabel?: boolean }>();
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1.5 text-danger"
        :class="{ 'rounded-full bg-danger/10 px-2.5 py-1 font-medium': showLabel }"
        :title="showLabel ? undefined : 'Needs attention'"
    >
        <span class="text-base leading-none" aria-hidden="true">⚑</span>
        <span :class="{ 'sr-only': !showLabel }">Needs attention</span>
    </span>
</template>
