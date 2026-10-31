<script setup>
/**
 * Scan a neaby QR with the camera, or upload a photo/screenshot of one.
 * Uses the native BarcodeDetector when present; otherwise lazy-loads jsQR
 * (only downloaded when someone actually opens the scanner).
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Camera, ImageUp, Loader2, ScanLine, X } from 'lucide-vue-next';
import Tip from '@/Components/Tip.vue';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);

const video = ref(null);
const status = ref('idle'); // idle | starting | scanning | decoding | error
const error = ref(null);
const fileInput = ref(null);
let stream = null;
let raf = null;
let detector = null;
let jsQR = null;
let canvas = null;

async function decoder() {
    if (detector === null && 'BarcodeDetector' in window) {
        try {
            const formats = await window.BarcodeDetector.getSupportedFormats?.();
            if (!formats || formats.includes('qr_code')) detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        } catch (_) { detector = false; }
    }
    if (!detector && !jsQR) jsQR = (await import('jsqr')).default;
}

async function decodeSource(src, w, h) {
    if (detector) {
        try {
            const codes = await detector.detect(src);
            if (codes[0]?.rawValue) return codes[0].rawValue;
        } catch (_) { /* fall through to jsQR */ }
        if (!jsQR) jsQR = (await import('jsqr')).default;
    }
    canvas ||= document.createElement('canvas');
    const k = Math.min(1, 900 / Math.max(w, h));
    canvas.width = Math.round(w * k);
    canvas.height = Math.round(h * k);
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(src, 0, 0, canvas.width, canvas.height);
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    return jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' })?.data || null;
}

function handle(value) {
    let url;
    try { url = new URL(value, window.location.origin); } catch (_) { url = null; }
    if (!url || url.host !== window.location.host) {
        error.value = url ? 'That QR code isn’t a neaby link.' : 'That QR code doesn’t contain a link.';
        status.value = 'error';
        return false;
    }
    stop();
    emit('close');
    router.visit(url.pathname + url.search + url.hash);
    return true;
}

async function start() {
    error.value = null;
    status.value = 'starting';
    try {
        await decoder();
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
        await nextTick();
        if (!video.value) return stop();
        video.value.srcObject = stream;
        await video.value.play();
        status.value = 'scanning';
        let last = 0;
        const loop = async (t) => {
            if (status.value !== 'scanning') return;
            if (t - last > 220 && video.value?.videoWidth) { // ~4 fps is plenty and easy on batteries
                last = t;
                const v = await decodeSource(video.value, video.value.videoWidth, video.value.videoHeight).catch(() => null);
                if (v && handle(v)) return;
            }
            raf = requestAnimationFrame(loop);
        };
        raf = requestAnimationFrame(loop);
    } catch (e) {
        status.value = 'error';
        error.value = e?.name === 'NotAllowedError' ? 'Camera permission was blocked — upload a photo of the code instead.' : 'No camera available — upload a photo of the code instead.';
    }
}

function stop() {
    cancelAnimationFrame(raf);
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
    if (status.value === 'scanning' || status.value === 'starting') status.value = 'idle';
}

async function upload(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    stop();
    status.value = 'decoding';
    error.value = null;
    try {
        await decoder();
        const bmp = await createImageBitmap(f);
        const v = await decodeSource(bmp, bmp.width, bmp.height);
        if (!v) { status.value = 'error'; error.value = 'Couldn’t find a QR code in that image. Try a sharper, closer photo.'; return; }
        handle(v);
    } catch (_) {
        status.value = 'error';
        error.value = 'Couldn’t read that image.';
    }
}

watch(() => props.show, (v) => (v ? start() : stop()));
onBeforeUnmount(stop);
function close() { stop(); emit('close'); }
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-[65] flex items-end justify-center bg-ink-950/50 p-0 backdrop-blur-md sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Scan a QR code" @click.self="close" @keydown.esc="close">
                <div class="w-full max-w-md overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl">
                    <div class="flex items-center gap-2 px-4 py-3">
                        <ScanLine :size="18" class="text-brand-600" />
                        <h2 class="font-display text-[15px] font-bold">Scan a neaby code</h2>
                        <button class="icon-btn ml-auto h-9 w-9" aria-label="Close" @click="close"><X :size="18" /></button>
                    </div>
                    <div class="relative mx-4 aspect-square overflow-hidden rounded-2xl bg-ink-900">
                        <video ref="video" class="h-full w-full object-cover" playsinline muted />
                        <div v-if="status === 'scanning'" class="pointer-events-none absolute inset-[18%] rounded-3xl border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,.35)]">
                            <div class="absolute inset-x-3 top-1/2 h-0.5 animate-pulse bg-brand-400" />
                        </div>
                        <div v-if="status === 'starting' || status === 'decoding'" class="absolute inset-0 flex items-center justify-center text-white/80"><Loader2 :size="26" class="animate-spin" /></div>
                        <div v-if="status === 'error' || status === 'idle'" class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-sm text-white/85">
                            <p>{{ error || 'Point your camera at a neaby QR code.' }}</p>
                            <button class="btn-sm bg-white/15 text-white hover:bg-white/25" @click="start"><Camera :size="15" /> Use camera</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-4">
                        <p class="flex-1 text-xs text-ink-500">Codes on posters, packaging or a seller’s counter add the item straight to your cart.</p>
                        <Tip label="Upload a photo of a code">
                            <button class="icon-btn-lg" aria-label="Upload a photo of a QR code" @click="fileInput.click()"><ImageUp :size="19" /></button>
                        </Tip>
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="upload" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
