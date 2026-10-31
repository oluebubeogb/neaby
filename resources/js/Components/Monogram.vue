<script setup>
defineProps({ size: { type: Number, default: 40 } });
</script>

<template>
    <span
        class="relative inline-flex items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-brand-400 via-brand-500 to-brand-700 font-brand font-bold lowercase text-white shadow-glow"
        :style="{ width: size + 'px', height: size + 'px', fontSize: size * 0.5 + 'px' }"
        aria-label="neaby"
    >
        <span class="relative -translate-y-[0.04em]">n</span>
        <span class="absolute right-[18%] top-[20%] h-[16%] w-[16%] rounded-full bg-white/90" />
    </span>
</template>
