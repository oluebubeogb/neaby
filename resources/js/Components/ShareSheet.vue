<script setup>
import { computed, ref, watch } from 'vue';
import { Check, Download, Link2, Mail, QrCode, Share2, X } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import Tip from '@/Components/Tip.vue';
import WhatsAppLine from '@/Components/icons/WhatsAppLine.vue';
import { xsrf } from '@/composables/http';

const props = defineProps({
    show: Boolean,
    kind: { type: String, default: 'product' }, // product | store | offer
    id: { type: Number, required: true },
    url: { type: String, required: true },
    title: { type: String, default: '' },
    text: { type: String, default: '' },
    cardBase: { type: String, required: true }, // PNG share card URL (no query)
    qrUrl: { type: String, default: null },
});
const emit = defineEmits(['close']);

// Format toggles are drawn as their own shape: □ square, ▯ tall, ▭ wide, QR.
const formats = [
    { key: 'square', label: 'Square · feed 1080×1080', ratio: 1, glyph: 'h-[15px] w-[15px]' },
    { key: 'portrait', label: 'Tall · status 1080×1350', ratio: 1080 / 1350, glyph: 'h-[17px] w-[13px]' },
    { key: 'landscape', label: 'Wide · link 1200×630', ratio: 1200 / 630, glyph: 'h-[11px] w-[19px]' },
];
const format = ref('square');
const showQr = ref(false);
const copied = ref(false);
const loaded = ref(false);
const busy = ref(false);
const withQuery = (u, q) => u + (u.includes('?') ? '&' : '?') + q;
const cardUrl = computed(() => withQuery(props.cardBase, `format=${format.value}`));
const current = computed(() => formats.find((f) => f.key === format.value));
const qrMode = computed(() => showQr.value && !!props.qrUrl);
const imageSrc = computed(() => (qrMode.value ? props.qrUrl : cardUrl.value));
const downloadSrc = computed(() => withQuery(imageSrc.value, 'download=1'));
const fileName = computed(() => `neaby-${qrMode.value ? 'qr' : format.value}.png`);
// Preview box keeps the card's exact aspect ratio and shrinks to the height left
// after the header + actions (see .share-preview below), so nothing is clipped.
const ratio = computed(() => (qrMode.value ? 1 : current.value.ratio));

// Shared links carry the chosen card format so the link preview (og:image) is
// the very card shown here — matters where an image can't be attached (WhatsApp Web, mailto).
const withCh = (ch) => withQuery(props.url, `ch=${ch}` + (qrMode.value || format.value === 'square' ? '' : `&card=${format.value}`));
const caption = (ch) => `${props.text || props.title} ${withCh(ch)}`.trim();

watch(() => props.show, (v) => { if (v) { showQr.value = false; copied.value = false; } });
watch(imageSrc, () => (loaded.value = false));

function track(channel) {
    try {
        fetch(route('events.share'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ kind: props.kind, id: props.id, channel }),
        });
    } catch (_) { /* best effort */ }
}

/*
 * The real PNG from ShareCards, fetched same-origin as a File. Prefetched as soon
 * as the preview loads so navigator.share() runs inside the tap's user activation
 * (Safari rejects a share that waits on a network fetch).
 */
const files = new Map();
async function fileFor(src) {
    if (files.has(src)) return files.get(src);
    const p = fetch(src, { credentials: 'same-origin' })
        .then((r) => (r.ok ? r.blob() : Promise.reject(new Error('card'))))
        .then((blob) => new File([blob], fileName.value, { type: 'image/png' }))
        .catch((e) => { files.delete(src); throw e; });
    files.set(src, p);
    return p;
}
function onLoaded() {
    loaded.value = true;
    if (typeof navigator !== 'undefined' && navigator.canShare) fileFor(imageSrc.value).catch(() => {});
}

/** Web Share with the card attached (+ caption & link). Resolves true if shared or dismissed by the user. */
async function shareWithImage(ch) {
    if (!navigator.canShare || !navigator.share) return false;
    let file;
    try { file = await fileFor(imageSrc.value); } catch (_) { return false; }
    const data = { files: [file], title: props.title, text: caption(ch) };
    if (!navigator.canShare(data)) return false;
    try {
        await navigator.share(data);
        track(ch);
    } catch (e) {
        if (e?.name !== 'AbortError') return false; // NotAllowedError etc. → fall back
    }
    return true;
}

async function whatsapp() {
    if (busy.value) return;
    busy.value = true;
    try {
        // Phones: the image itself goes to WhatsApp with the caption + link.
        if (await shareWithImage('whatsapp')) return;
        // Desktop / WhatsApp Web can't take a file through wa.me — the link's preview is this card.
        track('whatsapp');
        window.open(`https://wa.me/?text=${encodeURIComponent(caption('whatsapp'))}`, '_blank', 'noopener');
    } finally { busy.value = false; }
}

async function mail() {
    if (busy.value) return;
    busy.value = true;
    try {
        if (await shareWithImage('mail')) return;
        track('mail');
        const subject = props.title || 'On neaby';
        window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(caption('mail'))}`;
    } finally { busy.value = false; }
}

async function copy() {
    try { await navigator.clipboard.writeText(withCh('link')); copied.value = true; setTimeout(() => (copied.value = false), 2000); track('link'); } catch (_) {}
}

async function native() {
    if (busy.value) return;
    busy.value = true;
    try {
        if (await shareWithImage('native')) return;
        if (navigator.share) {
            try { await navigator.share({ title: props.title, text: props.text || props.title, url: withCh('native') }); track('native'); } catch (_) { /* dismissed */ }
            return;
        }
        await copy();
    } finally { busy.value = false; }
}
function downloaded() { track('download'); }
function pick(key) { format.value = key; showQr.value = false; }
</script>

<template>
    <Modal :show="show" max-width="lg" fit @close="emit('close')">
        <div class="flex flex-col p-4 sm:p-5">
            <div class="flex shrink-0 items-center gap-2">
                <h2 class="font-display text-base font-bold">Share</h2>

                <!-- Format toggles (icon-only) -->
                <div class="ml-auto flex items-center gap-1 rounded-2xl bg-ink-100 p-1" role="radiogroup" aria-label="Card format">
                    <Tip v-for="f in formats" :key="f.key" :label="f.label" side="bottom">
                        <button
                            role="radio"
                            :aria-checked="format === f.key && !showQr"
                            :aria-label="f.label"
                            class="flex h-8 w-9 items-center justify-center rounded-xl transition"
                            :class="format === f.key && !showQr ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-800'"
                            @click="pick(f.key)"
                        >
                            <span class="rounded-[3px] border-2 border-current" :class="f.glyph" />
                        </button>
                    </Tip>
                    <Tip v-if="qrUrl" label="QR code · print or stick on your counter" side="bottom">
                        <button
                            role="radio"
                            :aria-checked="showQr"
                            aria-label="QR code"
                            class="flex h-8 w-9 items-center justify-center rounded-xl transition"
                            :class="showQr ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-800'"
                            @click="showQr = true"
                        ><QrCode :size="17" /></button>
                    </Tip>
                </div>
                <button class="icon-btn h-9 w-9" aria-label="Close" @click="emit('close')"><X :size="18" /></button>
            </div>

            <div class="mt-3 flex shrink-0 justify-center">
                <div
                    class="share-preview relative overflow-hidden rounded-2xl bg-ink-100"
                    :class="qrMode ? 'share-preview-qr' : ''"
                    :style="{ '--ratio': ratio }"
                >
                    <div v-if="!loaded" class="absolute inset-0 animate-pulse bg-gradient-to-br from-ink-100 to-ink-200" />
                    <img
                        :key="imageSrc"
                        :src="imageSrc"
                        :alt="qrMode ? 'QR code' : `${title} share card`"
                        class="relative h-full w-full object-contain"
                        :class="qrMode ? 'p-3' : ''"
                        @load="onLoaded"
                        @error="loaded = true"
                    />
                </div>
            </div>

            <!-- Actions (icon-only, labelled on hover/focus) -->
            <div class="mt-4 flex shrink-0 flex-wrap items-center justify-center gap-3">
                <Tip label="WhatsApp">
                    <button class="icon-btn-primary h-12 w-12 rounded-full" aria-label="Share on WhatsApp" :disabled="busy" @click="whatsapp"><WhatsAppLine :size="21" /></button>
                </Tip>
                <Tip label="Mail">
                    <button class="icon-btn-lg h-12 w-12 rounded-full" aria-label="Share by mail" :disabled="busy" @click="mail"><Mail :size="20" /></button>
                </Tip>
                <Tip label="Share…">
                    <button class="icon-btn-lg h-12 w-12 rounded-full" aria-label="Share" :disabled="busy" @click="native"><Share2 :size="20" /></button>
                </Tip>
                <Tip :label="copied ? 'Copied!' : 'Copy link'">
                    <button class="icon-btn-lg h-12 w-12 rounded-full" :class="copied ? 'border-brand-300 text-brand-700' : ''" aria-label="Copy link" @click="copy"><component :is="copied ? Check : Link2" :size="20" /></button>
                </Tip>
                <Tip label="Save image">
                    <a class="icon-btn-lg h-12 w-12 rounded-full" :href="downloadSrc" :download="fileName" aria-label="Save image" @click="downloaded"><Download :size="20" /></a>
                </Tip>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
/*
 * Viewport-aware preview. Height is the smaller of
 *   · what's left of the visible viewport after the sheet chrome
 *     (outer gutter + padding + header + actions + safe areas), and
 *   · the sheet's inner width divided by the card's aspect ratio,
 * floored at 8rem (very short landscape phones: the sheet then scrolls as one unit).
 * Width follows from aspect-ratio, so the card is never cropped or stretched.
 */
.share-preview {
    --chrome: 12.25rem;
    --inner-w: calc(min(100vw - 1.5rem, 32rem) - 2rem);
    --avail-h: calc(100vh - var(--chrome) - env(safe-area-inset-top) - env(safe-area-inset-bottom));
    aspect-ratio: var(--ratio);
    height: max(8rem, min(var(--avail-h), var(--inner-w) / var(--ratio)));
    max-width: 100%;
}
@supports (height: 100dvh) {
    .share-preview {
        --avail-h: calc(100dvh - var(--chrome) - env(safe-area-inset-top) - env(safe-area-inset-bottom));
    }
}
@media (min-width: 640px) {
    .share-preview {
        --chrome: 14.5rem;
        --inner-w: calc(min(100vw - 3rem, 32rem) - 2.5rem);
    }
}
.share-preview-qr {
    height: max(8rem, min(var(--avail-h), var(--inner-w), 18rem));
}
</style>
