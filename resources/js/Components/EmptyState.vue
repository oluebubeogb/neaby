<script setup>
import IconCircle from '@/Components/IconCircle.vue';

defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' },
    color: { type: String, default: '#16b26a' },
});
</script>

<template>
    <div class="surface flex flex-col items-center px-6 py-12 text-center md:py-16">
        <IconCircle :color="color" :size="64"><slot name="icon" /></IconCircle>
        <h3 class="mt-4 font-display text-lg font-bold text-ink-900">{{ title }}</h3>
        <p v-if="body" class="mt-1.5 max-w-sm text-sm text-ink-500">{{ body }}</p>
        <div v-if="$slots.default" class="mt-5 flex flex-wrap justify-center gap-2"><slot /></div>
    </div>
</template>
