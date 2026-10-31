<script setup>
/** − n + stepper clamped to [min, max] (max = live stock). */
import { Minus, Plus } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: Number, default: 1 },
    min: { type: Number, default: 1 },
    max: { type: Number, default: 1 },
    disabled: Boolean,
    small: Boolean,
});
const emit = defineEmits(['update:modelValue', 'change']);
function set(v) {
    const n = Math.max(props.min, Math.min(props.max || props.min, Math.round(Number(v) || props.min)));
    emit('update:modelValue', n);
    emit('change', n);
}
</script>

<template>
    <div class="inline-flex items-center rounded-2xl border border-ink-200 bg-white" :class="small ? 'h-9' : 'h-11'" role="group" aria-label="Quantity">
        <button type="button" class="flex h-full items-center justify-center text-ink-600 transition hover:text-ink-900 disabled:opacity-30" :class="small ? 'w-8' : 'w-10'" :disabled="disabled || modelValue <= min" aria-label="Decrease quantity" @click="set(modelValue - 1)"><Minus :size="small ? 14 : 16" /></button>
        <input
            :value="modelValue"
            type="number"
            inputmode="numeric"
            :min="min"
            :max="max"
            :disabled="disabled"
            class="w-10 border-0 bg-transparent p-0 text-center text-sm font-bold tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
            aria-label="Quantity"
            @change="set($event.target.value)"
        />
        <button type="button" class="flex h-full items-center justify-center text-ink-600 transition hover:text-ink-900 disabled:opacity-30" :class="small ? 'w-8' : 'w-10'" :disabled="disabled || modelValue >= max" :title="modelValue >= max ? `Only ${max} in stock` : undefined" aria-label="Increase quantity" @click="set(modelValue + 1)"><Plus :size="small ? 14 : 16" /></button>
    </div>
</template>
