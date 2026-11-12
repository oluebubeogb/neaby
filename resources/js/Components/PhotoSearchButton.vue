<script setup>
/**
 * Camera / photo-library button for photo match. On phones the file picker
 * offers "Take photo" and the library; the image is shrunk on the device and
 * only sent when the shopper picks one.
 */
import { ref } from 'vue';
import { Camera, Loader2 } from 'lucide-vue-next';
import Tip from '@/Components/Tip.vue';
import { photoSearch } from '@/composables/assistant';

const props = defineProps({
    variant: { type: String, default: 'icon' }, // icon | pill
    underBalance: { type: Boolean, default: false },
    label: { type: String, default: 'Search by photo' },
});
const emit = defineEmits(['start', 'results', 'error']);
const input = ref(null);
const busy = ref(false);

function pick() { if (!busy.value) input.value?.click(); }

async function onFile(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    busy.value = true;
    const preview = URL.createObjectURL(file);
    emit('start', { preview });
    try {
        const res = await photoSearch(file, { underBalance: props.underBalance });
        emit('results', { ...res, preview });
    } catch (err) {
        emit('error', { message: err.status === 429 ? 'Too many photo searches — try again in a minute.' : (err.message || 'Photo search didn’t go through.'), preview });
    } finally {
        busy.value = false;
    }
}
defineExpose({ pick });
</script>

<template>
    <input ref="input" type="file" accept="image/*" class="hidden" @change="onFile" />
    <Tip v-if="variant === 'icon'" :label="label" side="bottom">
        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-ink-500 transition hover:bg-ink-100 hover:text-ink-900" :aria-label="label" :disabled="busy" @click="pick">
            <Loader2 v-if="busy" :size="18" class="animate-spin text-brand-600" />
            <Camera v-else :size="18" />
        </button>
    </Tip>
    <button v-else type="button" class="chip" :disabled="busy" @click="pick">
        <Loader2 v-if="busy" :size="15" class="animate-spin text-brand-600" />
        <Camera v-else :size="15" class="text-brand-600" />
        {{ label }}
    </button>
</template>
