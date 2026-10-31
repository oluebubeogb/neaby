<script setup>
defineProps({
    src: { type: String, default: null },
    initials: { type: String, default: '?' },
    size: { type: Number, default: 36 },
    accent: { type: String, default: '#16b26a' },
    rounded: { type: String, default: 'rounded-full' },
});
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center overflow-hidden font-display font-bold text-white ring-2 ring-white"
        :class="rounded"
        :style="{ width: size + 'px', height: size + 'px', fontSize: size * 0.38 + 'px', background: src ? '#eef2f0' : `linear-gradient(135deg, ${accent}, ${accent}cc)` }"
    >
        <img v-if="src" :src="src" alt="" class="h-full w-full object-cover" loading="lazy" />
        <span v-else>{{ initials }}</span>
    </span>
</template>
