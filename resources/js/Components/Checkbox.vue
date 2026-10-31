<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);
const props = defineProps({
    checked: { type: [Array, Boolean], required: true },
    value: { default: null },
});
const proxyChecked = computed({
    get: () => props.checked,
    set: (val) => emit('update:checked', val),
});
</script>

<template>
    <input v-model="proxyChecked" type="checkbox" :value="value" class="h-5 w-5 rounded-md border-ink-300 text-brand-500 shadow-sm focus:ring-brand-400" />
</template>
