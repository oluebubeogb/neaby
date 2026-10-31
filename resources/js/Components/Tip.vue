<script setup>
/** Hover / focus label. Wrap any icon-only control: <Tip label="Share"><button…/></Tip> */
const props = defineProps({
    label: { type: String, required: true },
    side: { type: String, default: 'top' }, // top | bottom | left | right
    wide: { type: Boolean, default: false },
    dark: { type: Boolean, default: false },
});
const pos = {
    top: 'bottom-[calc(100%+8px)] left-1/2 -translate-x-1/2',
    bottom: 'top-[calc(100%+8px)] left-1/2 -translate-x-1/2',
    left: 'right-[calc(100%+10px)] top-1/2 -translate-y-1/2',
    right: 'left-[calc(100%+10px)] top-1/2 -translate-y-1/2',
};
</script>

<template>
    <span class="group/tip relative inline-flex">
        <slot />
        <span
            role="tooltip"
            class="pointer-events-none absolute z-50 rounded-lg px-2.5 py-1 text-[11.5px] font-semibold leading-snug opacity-0 shadow-lift backdrop-blur transition duration-150 group-hover/tip:opacity-100 group-focus-within/tip:opacity-100"
            :class="[pos[side], wide ? 'w-56 whitespace-normal text-left font-medium' : 'whitespace-nowrap', dark ? 'bg-white/95 text-ink-900' : 'bg-ink-900/90 text-white']"
        >{{ label }}</span>
    </span>
</template>
