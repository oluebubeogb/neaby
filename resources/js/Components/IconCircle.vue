<script setup>
import { computed } from 'vue';

const props = defineProps({
    color: { type: String, default: '#16b26a' },
    size: { type: Number, default: 44 },
});
const style = computed(() => ({
    width: props.size + 'px',
    height: props.size + 'px',
    color: props.color,
    background: `radial-gradient(120% 120% at 30% 20%, ${props.color}26, ${props.color}10 60%, ${props.color}08)`,
    boxShadow: `inset 0 0 0 1px ${props.color}1f`,
}));
</script>

<template>
    <span class="inline-flex shrink-0 items-center justify-center rounded-full" :style="style">
        <slot />
    </span>
</template>
