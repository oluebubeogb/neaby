<script setup>
import { computed, ref, watch } from 'vue';
import { Check, Copy, Download, MessageCircle, QrCode, Share2, X } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';

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

const formats = [
    { key: 'square', label: 'Square', hint: 'Feed · 1080×1080', aspect: 'aspect-square' },
    { key: 'portrait', label: 'Portrait', hint: 'Status · 1080×1350', aspect: 'aspect-[4/5]' },
    { key: 'landscape', label: 'Wide', hint: 'Link · 1200×630', aspect: 'aspect-[1200/630]' },
];
const format = ref('square');
const showQr = ref(false);
const copied = ref(false);
const sep = computed(() => (props.cardBase.includes('?') ? '&' : '?'));
const cardUrl = computed(() => `${props.cardBase}${sep.value}format=${format.value}`);
const current = computed(() => formats.find((f) => f.key === format.value));
const withCh = (ch) => props.url + (props.url.includes('?') ? '&' : '?') + 'ch=' + ch;

watch(() => props.show, (v) => { if (v) { showQr.value = false; copied.value = false; } });

function xsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}
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

const message = computed(() => `${props.text || props.title} ${withCh('whatsapp')}`.trim());
function whatsapp() {
    track('whatsapp');
    window.open(`https://wa.me/?text=${encodeURIComponent(message.value)}`, '_blank', 'noopener');
}
async function copy() {
    try { await navigator.clipboard.writeText(withCh('link')); copied.value = true; setTimeout(() => (copied.value = false), 2000); track('link'); } catch (_) {}
}
async function native() {
    const data = { title: props.title, text: props.text || props.title, url: withCh('native') };
    try {
        // Try sharing the image file itself when supported (WhatsApp Status, Instagram…)
        if (navigator.canShare) {
            const blob = await (await fetch(cardUrl.value)).blob();
            const file = new File([blob], `neaby-${format.value}.png`, { type: 'image/png' });
            if (navigator.canShare({ files: [file] })) { await navigator.share({ ...data, files: [file] }); track('native'); return; }
        }
        if (navigator.share) { await navigator.share(data); track('native'); return; }
        await copy();
    } catch (_) { /* dismissed */ }
}
function downloaded() { track('download'); }
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-bold">Share</h2>
                <button class="icon-btn" aria-label="Close" @click="emit('close')"><X :size="18" /></button>
            </div>

            <div class="mt-4 flex gap-2">
                <button v-for="f in formats" :key="f.key" class="chip" :class="format === f.key && !showQr ? 'chip-active' : ''" @click="format = f.key; showQr = false">{{ f.label }}</button>
                <button v-if="qrUrl" class="chip ml-auto" :class="showQr ? 'chip-active' : ''" @click="showQr = !showQr"><QrCode :size="14" /> QR</button>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl bg-ink-100">
                <img v-if="showQr && qrUrl" :src="qrUrl" alt="QR code" class="mx-auto aspect-square w-full max-w-xs object-contain p-4" />
                <img v-else :key="cardUrl" :src="cardUrl" :alt="`${title} share card`" class="mx-auto w-full object-contain" :class="[current.aspect, format === 'portrait' ? 'max-h-[46vh]' : '']" />
            </div>
            <p class="hint mt-2">{{ showQr ? 'Print it or stick it on your shop counter — scans open this page.' : current.hint }}</p>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button class="btn-primary" @click="whatsapp"><MessageCircle :size="16" /> WhatsApp</button>
                <button class="btn-ghost" @click="native"><Share2 :size="16" /> Share</button>
                <button class="btn-ghost" @click="copy"><component :is="copied ? Check : Copy" :size="16" /> {{ copied ? 'Copied' : 'Copy link' }}</button>
                <a class="btn-ghost" :href="showQr && qrUrl ? qrUrl : cardUrl" :download="`neaby-${showQr ? 'qr' : format}.png`" @click="downloaded"><Download :size="16" /> Save</a>
            </div>
        </div>
    </Modal>
</template>
