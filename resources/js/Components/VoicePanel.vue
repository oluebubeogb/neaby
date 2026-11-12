<script setup>
/**
 * "Shop with me" voice session. Opens only when the shopper taps the mic —
 * never on its own, never on the feed. Browser speech-to-text + text-to-speech;
 * typed fallback when the browser has no speech recognition. Every suggestion
 * is a real in-stock listing with one-tap Add to cart.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Loader2, Mic, MicOff, Send, Sparkles, Volume2, VolumeX, X } from 'lucide-vue-next';
import ProductCard from '@/Components/ProductCard.vue';
import PhotoSearchButton from '@/Components/PhotoSearchButton.vue';
import NearMe from '@/Components/NearMe.vue';
import { closeVoice, speak, speechRecognition, stopSpeaking } from '@/composables/assistant';
import { cartAdded } from '@/composables/cartFx';
import { loginUrl } from '@/composables/auth';
import { postJson } from '@/composables/http';

const page = usePage();
const turns = ref([]); // {who: 'you'|'neaby', text}
const items = ref([]);
const text = ref('');
const interim = ref('');
const listening = ref(false);
const busy = ref(false);
const voiceOn = ref(true);
const needs = ref({ login: false, location: false, photo: false });
const supported = ref(false);
const log = ref(null);
const inputEl = ref(null);
let rec = null;

const hello = computed(() => (page.props.auth?.user ? `Hi ${page.props.auth.user.name.split(' ')[0]}! ` : 'Hi! ') + 'Tell me what you’re looking for — like “black heels near me under 20k”.');

onMounted(() => {
    turns.value.push({ who: 'neaby', text: hello.value });
    rec = speechRecognition();
    supported.value = !!rec;
    if (rec) {
        rec.onresult = (e) => {
            let fin = '';
            let mid = '';
            for (const r of e.results) (r.isFinal ? (fin += r[0].transcript) : (mid += r[0].transcript));
            interim.value = mid;
            if (fin.trim()) { interim.value = ''; send(fin.trim()); }
        };
        rec.onend = () => (listening.value = false);
        rec.onerror = (e) => {
            listening.value = false;
            if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                supported.value = false;
                turns.value.push({ who: 'neaby', text: 'Microphone is blocked — you can type instead.' });
                nextTick(() => inputEl.value?.focus());
            }
        };
        listen(); // the shopper just tapped the mic to open this
    } else {
        nextTick(() => inputEl.value?.focus());
    }
    document.documentElement.classList.add('overflow-hidden');
});
onBeforeUnmount(() => {
    try { rec?.abort(); } catch (_) {}
    stopSpeaking();
    document.documentElement.classList.remove('overflow-hidden');
});

function listen() {
    if (!rec || busy.value) return;
    if (listening.value) { try { rec.stop(); } catch (_) {} return; }
    stopSpeaking();
    try { rec.start(); listening.value = true; } catch (_) { listening.value = false; }
}

async function scrollLog() { await nextTick(); log.value?.scrollTo({ top: log.value.scrollHeight, behavior: 'smooth' }); }

async function send(said = null) {
    const t = (said ?? text.value).trim();
    if (!t || busy.value) return;
    text.value = '';
    turns.value.push({ who: 'you', text: t });
    scrollLog();
    busy.value = true;
    needs.value = { login: false, location: false, photo: false };
    try {
        const res = await postJson(route('assistant.voice'), { text: t });
        turns.value.push({ who: 'neaby', text: res.reply });
        speak(res.reply, voiceOn.value);
        if (Array.isArray(res.items)) items.value = res.items;
        const c = res.client || [];
        needs.value = { login: c.includes('login'), location: c.includes('need_location'), photo: c.includes('open_photo') };
        if (c.includes('cart_updated')) { cartAdded(1); router.reload({ only: ['counts'] }); }
        if (c.includes('reload_near')) router.reload();
        if (res.open) { closeVoice(); router.visit(res.open); }
    } catch (e) {
        turns.value.push({ who: 'neaby', text: e.status === 429 ? 'Give me a minute — too many requests.' : 'Sorry, that didn’t go through. Try again?' });
    } finally {
        busy.value = false;
        scrollLog();
    }
}

function onPhoto(res) {
    items.value = res.items || [];
    const msg = res.ok === false ? res.message : res.items?.length ? `Here are ${res.items.length} look-alikes in stock.` : res.message;
    turns.value.push({ who: 'neaby', text: msg });
    speak(msg, voiceOn.value);
    scrollLog();
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[70] flex items-end justify-center md:items-center" role="dialog" aria-modal="true" aria-label="Shop with me">
            <div class="scrim absolute inset-0" @click="closeVoice" />
            <section class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-lift md:max-w-2xl md:rounded-[2rem]">
                <header class="flex items-center gap-2 border-b border-ink-100 px-4 py-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-50 text-brand-700"><Sparkles :size="17" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-base font-bold text-ink-900">Shop with me</h2>
                        <p class="truncate text-xs text-ink-500">Only real, in-stock items from neaby stores</p>
                    </div>
                    <button class="icon-btn" :aria-label="voiceOn ? 'Mute replies' : 'Speak replies'" @click="voiceOn = !voiceOn; voiceOn || stopSpeaking()">
                        <Volume2 v-if="voiceOn" :size="18" /><VolumeX v-else :size="18" />
                    </button>
                    <button class="icon-btn" aria-label="Close" @click="closeVoice"><X :size="19" /></button>
                </header>

                <div ref="log" class="flex-1 space-y-2 overflow-y-auto px-4 py-3">
                    <div v-for="(t, i) in turns" :key="i" class="flex" :class="t.who === 'you' ? 'justify-end' : 'justify-start'">
                        <p class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm leading-relaxed" :class="t.who === 'you' ? 'bg-ink-900 text-white' : 'bg-brand-50 text-ink-800'">{{ t.text }}</p>
                    </div>
                    <p v-if="interim" class="text-right text-sm italic text-ink-400">{{ interim }}…</p>
                    <div v-if="busy" class="flex items-center gap-2 text-sm text-ink-500"><Loader2 :size="15" class="animate-spin text-brand-600" /> Looking…</div>

                    <div v-if="needs.login" class="flex justify-start"><Link :href="loginUrl()" class="btn-primary btn-sm" @click="closeVoice">Sign in</Link></div>
                    <div v-if="needs.location" class="flex items-center gap-2 text-xs text-ink-500">Rank by distance: <NearMe /></div>

                    <div v-if="items.length" class="no-scrollbar -mx-4 flex gap-3 overflow-x-auto px-4 pb-1 pt-2">
                        <div v-for="(p, i) in items" :key="p.id" class="relative w-40 shrink-0 sm:w-44">
                            <span class="absolute left-2 top-2 z-10 flex h-6 min-w-[24px] items-center justify-center rounded-full bg-ink-900/80 px-1.5 text-[11px] font-bold text-white">{{ i + 1 }}</span>
                            <ProductCard :product="p" @click="closeVoice" />
                        </div>
                    </div>
                </div>

                <footer class="border-t border-ink-100 px-4 pb-safe pt-3">
                    <form class="flex items-center gap-2" @submit.prevent="send()">
                        <button
                            v-if="supported"
                            type="button"
                            class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-white shadow-glow transition active:scale-95"
                            :class="listening ? 'bg-red-500' : 'bg-brand-500 hover:bg-brand-600'"
                            :aria-label="listening ? 'Stop listening' : 'Speak'"
                            :disabled="busy"
                            @click="listen"
                        >
                            <span v-if="listening" class="absolute inset-0 animate-ping rounded-full bg-red-400/40" />
                            <MicOff v-if="listening" :size="24" class="relative" /><Mic v-else :size="24" class="relative" />
                        </button>
                        <input ref="inputEl" v-model="text" type="text" maxlength="400" enterkeyhint="send" class="input flex-1 rounded-full py-3" :placeholder="supported ? 'Or type here…' : 'Type what you need…'" />
                        <PhotoSearchButton v-if="page.props.features?.photoSearch" :class="needs.photo ? 'ring-2 ring-brand-400 rounded-full' : ''" @results="onPhoto" @error="(e) => onPhoto({ ok: false, message: e.message })" />
                        <button class="icon-btn-primary rounded-full" :disabled="busy || !text.trim()" aria-label="Send"><Send :size="18" /></button>
                    </form>
                    <p class="mt-2 text-center text-[11px] text-ink-400">Try “gift for my wife under 30k”, “add the second one”, “I’m going to Abuja”.</p>
                </footer>
            </section>
        </div>
    </Teleport>
</template>
