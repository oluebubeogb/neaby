<script setup>
import { onMounted, ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });
const model = defineModel({ type: String, required: true });
defineProps({ autocomplete: { type: String, default: 'current-password' }, id: { type: String, default: 'password' } });

const show = ref(false);
const input = ref(null);
onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) input.value.focus();
});
defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            ref="input"
            v-model="model"
            :type="show ? 'text' : 'password'"
            :autocomplete="autocomplete"
            class="input pr-12"
            v-bind="$attrs"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-1.5 my-auto flex h-9 w-9 items-center justify-center rounded-xl text-ink-400 transition hover:bg-ink-100 hover:text-ink-700"
            :aria-label="show ? 'Hide password' : 'Show password'"
            :aria-pressed="show"
            @click="show = !show"
        >
            <EyeOff v-if="show" :size="18" />
            <Eye v-else :size="18" />
        </button>
    </div>
</template>
