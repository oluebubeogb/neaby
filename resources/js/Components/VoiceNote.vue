<script setup>
/** Compact voice-note player. preload="none": nothing downloads until you tap play. */
import { onBeforeUnmount, ref } from 'vue';
import { Loader2, Pause, Play } from 'lucide-vue-next';
import { formatClock } from '@/composables/media';

const props = defineProps({ src: String, duration: { type: Number, default: 0 }, mine: Boolean });
const audio = ref(null);
const playing = ref(false);
const loading = ref(false);
const t = ref(0);
const bars = Array.from({ length: 28 }, (_, i) => 30 + Math.round(Math.abs(Math.sin(i * 1.7 + props.duration)) * 70));

function toggle() {
    const a = audio.value;
    if (!a) return;
    if (a.paused) {
        document.querySelectorAll('audio[data-vn]').forEach((x) => x !== a && x.pause());
        loading.value = a.readyState < 2;
        a.play().catch(() => (loading.value = false));
    } else a.pause();
}
onBeforeUnmount(() => audio.value?.pause());
</script>

<template>
    <div class="flex w-56 max-w-full items-center gap-2.5">
        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition" :class="mine ? 'bg-white/20 hover:bg-white/30' : 'bg-brand-500 text-white hover:bg-brand-600'" :aria-label="playing ? 'Pause voice note' : 'Play voice note'" @click="toggle">
            <Loader2 v-if="loading" :size="16" class="animate-spin" />
            <Pause v-else-if="playing" :size="16" />
            <Play v-else :size="16" class="ml-0.5" />
        </button>
        <div class="flex h-7 flex-1 items-center gap-[2px]" aria-hidden="true">
            <span v-for="(b, i) in bars" :key="i" class="w-[3px] rounded-full transition-colors" :style="{ height: b + '%' }" :class="(i / bars.length) * (duration || 1) < t ? (mine ? 'bg-white' : 'bg-brand-600') : mine ? 'bg-white/40' : 'bg-ink-300'" />
        </div>
        <span class="w-9 text-right text-[11px] tabular-nums opacity-80">{{ formatClock(playing || t ? t : duration) }}</span>
        <audio ref="audio" data-vn :src="src" preload="none" @playing="playing = true; loading = false" @pause="playing = false" @ended="playing = false; t = 0" @timeupdate="t = $event.target.currentTime" />
    </div>
</template>
