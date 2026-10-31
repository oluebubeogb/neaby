<script setup>
defineProps({
    size: { type: String, default: 'md' }, // sm | md | lg | xl
    tone: { type: String, default: 'ink' }, // ink | white
});
const sizes = { sm: 'text-xl', md: 'text-2xl', lg: 'text-3xl', xl: 'text-5xl md:text-6xl' };
</script>

<template>
    <span
        class="inline-flex select-none items-baseline font-brand font-bold lowercase leading-none tracking-[-0.03em]"
        :class="[sizes[size], tone === 'white' ? 'text-white' : 'text-ink-900']"
        aria-label="neaby"
    >
        neaby<span
            class="ml-[0.06em] inline-block h-[0.24em] w-[0.24em] translate-y-[-0.02em] rounded-full bg-gradient-to-br from-brand-400 to-brand-600 shadow-[0_0_0_0.06em_rgba(22,178,106,.18)]"
            aria-hidden="true"
        />
    </span>
</template>
