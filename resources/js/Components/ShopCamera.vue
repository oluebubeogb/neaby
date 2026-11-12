<script setup>
/**
 * Continuous shop camera: the rear camera stays open, every shutter tap grabs a
 * still, thumbnails pile up (remove any), max `max` in total. No instructions —
 * just the camera, the shots, and the count. v-model = File[] of new shots.
 * Falls back to the native camera picker when live video isn't available.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Check, Loader2, SwitchCamera, X } from 'lucide-vue-next';

const props = defineProps({
    show: Boolean,
    existing: { type: Number, default: 0 }, // already-saved photos (count toward max)
    max: { type: Number, default: 10 },
});
const shots = defineModel({ type: Array, default: () => [] });
const emit = defineEmits(['close']);

const video = ref(null);
const fallback = ref(null);
const starting = ref(false);
const failed = ref(false);
const flash = ref(false);
const facing = ref('environment');
const canFlip = ref(false);
let stream = null;

const previews = ref([]); // object URLs in step with shots
const left = computed(() => props.max - props.existing - shots.value.length);
const total = computed(() => props.existing + shots.value.length);

watch(shots, (list) => {
    // keep previews in sync when the parent clears/removes shots
    if (list.length < previews.value.length) {
        previews.value.slice(list.length).forEach((u) => URL.revokeObjectURL(u));
        previews.value = list.map((f, i) => previews.value[i] || URL.createObjectURL(f));
    }
});

async function start() {
    stop();
    failed.value = false;
    starting.value = true;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: facing.value }, width: { ideal: 1920 }, height: { ideal: 1440 } },
            audio: false,
        });
        await nextTick();
        if (video.value) {
            video.value.srcObject = stream;
            await video.value.play().catch(() => {});
        }
        const cams = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
        canFlip.value = cams.length > 1;
    } catch (_) {
        failed.value = true;
    } finally {
        starting.value = false;
    }
}
function stop() {
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
}

watch(() => props.show, (v) => (v ? start() : stop()));
onBeforeUnmount(() => { stop(); previews.value.forEach((u) => URL.revokeObjectURL(u)); });

function add(file) {
    if (left.value <= 0) return;
    shots.value = [...shots.value, file];
    previews.value = [...previews.value, URL.createObjectURL(file)];
}

function capture() {
    const v = video.value;
    if (!v || !v.videoWidth || left.value <= 0) return;
    const scale = Math.min(1, 1600 / Math.max(v.videoWidth, v.videoHeight));
    const c = document.createElement('canvas');
    c.width = Math.round(v.videoWidth * scale);
    c.height = Math.round(v.videoHeight * scale);
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
    flash.value = true;
    setTimeout(() => (flash.value = false), 120);
    c.toBlob((blob) => blob && add(new File([blob], `shop-${Date.now()}.jpg`, { type: 'image/jpeg' })), 'image/jpeg', 0.85);
}

function remove(i) {
    URL.revokeObjectURL(previews.value[i]);
    previews.value = previews.value.filter((_, k) => k !== i);
    shots.value = shots.value.filter((_, k) => k !== i);
}

function flip() {
    facing.value = facing.value === 'environment' ? 'user' : 'environment';
    start();
}

function picked(e) {
    for (const f of Array.from(e.target.files || [])) add(f);
    e.target.value = '';
}

function onKey(e) {
    if (e.key === 'Escape') emit('close');
    else if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); capture(); }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[90] flex flex-col bg-black text-white" role="dialog" aria-modal="true" aria-label="Shop camera" tabindex="-1" @keydown="onKey">
            <div class="relative min-h-0 flex-1">
                <video ref="video" class="absolute inset-0 h-full w-full object-cover" playsinline muted autoplay />
                <div v-if="flash" class="absolute inset-0 bg-white/70" />
                <div v-if="starting" class="absolute inset-0 flex items-center justify-center"><Loader2 :size="28" class="animate-spin text-white/70" /></div>
                <div v-if="failed" class="absolute inset-0 flex items-center justify-center">
                    <label class="flex h-20 w-20 cursor-pointer items-center justify-center rounded-full bg-white/10 ring-2 ring-white/40" aria-label="Take a photo">
                        <span class="h-14 w-14 rounded-full bg-white" />
                        <input ref="fallback" type="file" accept="image/*" capture="environment" multiple class="hidden" @change="picked" />
                    </label>
                </div>

                <div class="absolute inset-x-0 top-0 flex items-center gap-2 bg-gradient-to-b from-black/60 to-transparent px-4 pb-8 pt-[calc(env(safe-area-inset-top)+12px)]">
                    <button type="button" class="immersive-btn h-10 w-10" aria-label="Close" @click="emit('close')"><X :size="20" /></button>
                    <span class="ml-auto rounded-full bg-black/40 px-3 py-1 text-sm font-bold tabular-nums">{{ total }} / {{ max }}</span>
                    <button v-if="canFlip" type="button" class="immersive-btn h-10 w-10" aria-label="Switch camera" @click="flip"><SwitchCamera :size="19" /></button>
                </div>
            </div>

            <div class="shrink-0 bg-black px-4 pb-[calc(env(safe-area-inset-bottom)+16px)] pt-3">
                <div class="no-scrollbar flex min-h-[64px] gap-2 overflow-x-auto">
                    <div v-for="(src, i) in previews" :key="src" class="relative h-16 w-16 shrink-0 overflow-hidden rounded-xl ring-1 ring-white/20">
                        <img :src="src" alt="" class="h-full w-full object-cover" />
                        <button type="button" class="absolute right-0.5 top-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-black/70" :aria-label="`Remove shot ${i + 1}`" @click="remove(i)"><X :size="13" /></button>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <span class="w-12" />
                    <button
                        type="button"
                        class="flex h-[72px] w-[72px] items-center justify-center rounded-full ring-4 ring-white/80 transition active:scale-95 disabled:opacity-30"
                        :disabled="left <= 0 || failed || starting"
                        aria-label="Capture"
                        @click="capture"
                    ><span class="h-[58px] w-[58px] rounded-full bg-white" /></button>
                    <button type="button" class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-500 text-white shadow-glow" aria-label="Done" @click="emit('close')"><Check :size="22" :stroke-width="3" /></button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
