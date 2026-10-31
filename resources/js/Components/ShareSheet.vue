<script setup>
import { computed, ref, watch } from 'vue';
import { Check, Download, Link2, QrCode, Share2, X } from 'lucide-vue-next';
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
    { key: 'square', label: 'Square · feed 1080×1080', aspect: 'aspect-square', glyph: 'h-[15px] w-[15px]' },
    { key: 'portrait', label: 'Tall · status 1080×1350', aspect: 'aspect-[4/5]', glyph: 'h-[17px] w-[13px]' },
    { key: 'landscape', label: 'Wide · link 1200×630', aspect: 'aspect-[1200/630]', glyph: 'h-[11px] w-[19px]' },
];
const format = ref('square');
const showQr = ref(false);
const copied = ref(false);
const loaded = ref(false);
const sep = computed(() => (props.cardBase.includes('?') ? '&' : '?'));
const cardUrl = computed(() => `${props.cardBase}${sep.value}format=${format.value}`);
const current = computed(() => formats.find((f) => f.key === format.value));
const withCh = (ch) => props.url + (props.url.includes('?') ? '&' : '?') + 'ch=' + ch;

watch(() => props.show, (v) => { if (v) { showQr.value = false; copied.value = false; } });
watch([cardUrl, showQr], () => (loaded.value = false));

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
        // Share the image itself when supported (WhatsApp Status, Instagram…)
        if (navigator.canShare) {
            const src = showQr.value && props.qrUrl ? props.qrUrl : cardUrl.value;
            const blob = await (await fetch(src)).blob();
            const file = new File([blob], `neaby-${showQr.value ? 'qr' : format.value}.png`, { type: 'image/png' });
            if (navigator.canShare({ files: [file] })) { await navigator.share({ ...data, files: [file] }); track('native'); return; }
        }
        if (navigator.share) { await navigator.share(data); track('native'); return; }
        await copy();
    } catch (_) { /* dismissed */ }
}
function downloaded() { track('download'); }
function pick(key) { format.value = key; showQr.value = false; }
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-4 sm:p-5">
            <div class="flex items-center gap-2">
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

            <div class="relative mt-3 overflow-hidden rounded-2xl bg-ink-100">
                <div v-if="!loaded" class="absolute inset-0 animate-pulse bg-gradient-to-br from-ink-100 to-ink-200" />
                <img v-if="showQr && qrUrl" :src="qrUrl" alt="QR code" class="relative mx-auto aspect-square w-full max-w-[18rem] object-contain p-3" @load="loaded = true" />
                <img
                    v-else
                    :key="cardUrl"
                    :src="cardUrl"
                    :alt="`${title} share card`"
                    class="relative mx-auto w-full object-contain"
                    :class="[current.aspect, format === 'portrait' ? 'max-h-[52vh] w-auto' : '']"
                    @load="loaded = true"
                />
            </div>

            <!-- Actions (icon-only, labelled on hover/focus) -->
            <div class="mt-4 flex items-center justify-center gap-3">
                <Tip label="WhatsApp">
                    <button class="icon-btn-primary h-12 w-12 rounded-full" aria-label="Share on WhatsApp" @click="whatsapp"><WhatsAppLine :size="21" /></button>
                </Tip>
                <Tip label="Share…">
                    <button class="icon-btn-lg h-12 w-12 rounded-full" aria-label="Share" @click="native"><Share2 :size="20" /></button>
                </Tip>
                <Tip :label="copied ? 'Copied!' : 'Copy link'">
                    <button class="icon-btn-lg h-12 w-12 rounded-full" :class="copied ? 'border-brand-300 text-brand-700' : ''" aria-label="Copy link" @click="copy"><component :is="copied ? Check : Link2" :size="20" /></button>
                </Tip>
                <Tip label="Save image">
                    <a class="icon-btn-lg h-12 w-12 rounded-full" :href="showQr && qrUrl ? qrUrl : cardUrl" :download="`neaby-${showQr ? 'qr' : format}.png`" aria-label="Save image" @click="downloaded"><Download :size="20" /></a>
                </Tip>
            </div>
        </div>
    </Modal>
</template>
