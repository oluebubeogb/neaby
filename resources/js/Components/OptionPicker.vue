<script setup>
/** Seller-defined options (e.g. Size: S, M, L) as chip rows. v-model is { Size: 'M' }. */
const props = defineProps({
    options: { type: Array, default: () => [] }, // [{ name, values: [] }]
    modelValue: { type: Object, default: () => ({}) },
    error: { type: String, default: null },
    disabled: Boolean,
});
const emit = defineEmits(['update:modelValue']);
function pick(name, v) { if (!props.disabled) emit('update:modelValue', { ...props.modelValue, [name]: v }); }
</script>

<template>
    <div v-if="options.length" class="grid gap-3">
        <div v-for="o in options" :key="o.name">
            <div class="mb-1.5 text-[11px] font-bold uppercase tracking-[.12em] text-ink-500">{{ o.name }} <span v-if="modelValue[o.name]" class="normal-case tracking-normal text-ink-800">· {{ modelValue[o.name] }}</span></div>
            <div class="flex flex-wrap gap-1.5" role="radiogroup" :aria-label="o.name">
                <button
                    v-for="v in o.values"
                    :key="v"
                    type="button"
                    role="radio"
                    :aria-checked="modelValue[o.name] === v"
                    class="min-w-[2.5rem] rounded-xl border px-3 py-1.5 text-[13px] font-semibold transition"
                    :class="modelValue[o.name] === v ? 'border-ink-900 bg-ink-900 text-white' : 'border-ink-200 bg-white text-ink-700 hover:border-ink-400'"
                    :disabled="disabled"
                    @click="pick(o.name, v)"
                >{{ v }}</button>
            </div>
        </div>
        <p v-if="error" class="text-xs font-medium text-red-600">{{ error }}</p>
    </div>
</template>
