<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ArrowLeft, ImagePlus, Loader2, MessageCircle, Mic, Phone, PhoneMissed, Send, Square, Trash2, X } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import Tip from '@/Components/Tip.vue';
import VoiceNote from '@/Components/VoiceNote.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { getJson, postJson } from '@/composables/http';
import { compressImage, formatClock, voiceMime } from '@/composables/media';
import { call, startCall } from '@/composables/call';

const props = defineProps({ threads: Array, active: Object, draft: String, limits: Object, seo: Object });
const page = usePage();

const messages = ref([]);
const hasMore = ref(false);
const otherReadAt = ref(null);
const loading = ref(false);
const loadingOlder = ref(false);
const sending = ref(false);
const error = ref(null);
const text = ref(props.draft || '');
const list = ref(null);
const input = ref(null);
const fileInput = ref(null);
const viewer = ref(null);
let pollTimer = null;

const other = computed(() => props.active?.other);
const lastId = computed(() => messages.value.reduce((m, x) => (typeof x.id === 'number' && x.id > m ? x.id : m), 0));
const lastMine = computed(() => [...messages.value].reverse().find((m) => m.mine && typeof m.id === 'number'));
const seen = computed(() => lastMine.value && otherReadAt.value && new Date(otherReadAt.value) >= new Date(lastMine.value.at));
const canCall = computed(() => props.limits?.calls && page.props.rtc?.calls);

// ── history ───────────────────────────────────────────────
function atBottom() { const el = list.value; return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120; }
async function toBottom(smooth = false) { await nextTick(); list.value?.scrollTo({ top: list.value.scrollHeight, behavior: smooth ? 'smooth' : 'auto' }); }

async function load() {
    if (!props.active) return;
    loading.value = true;
    try {
        const r = await getJson(route('messages.feed', props.active.id));
        messages.value = r.messages;
        hasMore.value = r.has_more;
        otherReadAt.value = r.other_read_at;
        await toBottom();
    } catch (e) { error.value = e.message; }
    loading.value = false;
    schedule();
}
async function older() {
    if (!hasMore.value || loadingOlder.value) return;
    const first = messages.value.find((m) => typeof m.id === 'number');
    if (!first) return;
    loadingOlder.value = true;
    const el = list.value;
    const before = el.scrollHeight;
    try {
        const r = await getJson(route('messages.feed', { conversation: props.active.id, before: first.id }));
        messages.value = [...r.messages, ...messages.value];
        hasMore.value = r.has_more;
        await nextTick();
        el.scrollTop += el.scrollHeight - before;
    } catch (_) {}
    loadingOlder.value = false;
}
function onScroll() { if (list.value?.scrollTop < 80) older(); }

async function poll() {
    if (!props.active) return;
    if (document.visibilityState === 'visible') {
        try {
            const stick = atBottom();
            const r = await getJson(route('messages.feed', { conversation: props.active.id, after: lastId.value }));
            const known = new Set(messages.value.map((m) => m.id));
            const fresh = r.messages.filter((m) => !known.has(m.id));
            if (fresh.length) { messages.value.push(...fresh); if (stick) toBottom(true); }
            otherReadAt.value = r.other_read_at;
        } catch (_) {}
    }
    schedule();
}
function schedule() {
    clearTimeout(pollTimer);
    // Back off while idle in a hidden tab; snappy while chatting.
    const s = document.visibilityState === 'visible' ? props.limits?.poll_seconds || 4 : 30;
    pollTimer = setTimeout(poll, s * 1000);
}
function onVis() { if (document.visibilityState === 'visible') poll(); }

// ── sending ───────────────────────────────────────────────
function optimistic(m) {
    const tmp = { id: `tmp-${Date.now()}-${Math.random()}`, mine: true, at: new Date().toISOString(), meta: {}, pending: true, ...m };
    messages.value.push(tmp);
    toBottom(true);
    return tmp;
}
function settle(tmp, real) {
    const i = messages.value.indexOf(tmp);
    if (i >= 0) messages.value.splice(i, 1, real);
}
function fail(tmp, e) {
    tmp.pending = false;
    tmp.failed = true;
    error.value = e.message;
}

async function sendText() {
    const body = text.value.trim();
    if (!body || !props.active) return;
    text.value = '';
    error.value = null;
    const tmp = optimistic({ type: 'text', body });
    try {
        const r = await postJson(route('messages.send', props.active.id), { type: 'text', body });
        settle(tmp, r.message);
    } catch (e) { fail(tmp, e); text.value = body; }
    input.value?.focus();
}
function onEnter(e) { if (!e.shiftKey && !e.isComposing) { e.preventDefault(); sendText(); } }

async function pickImage(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f || !props.active) return;
    error.value = null;
    sending.value = true;
    let tmp = null;
    try {
        const { file, w, h } = await compressImage(f);
        const preview = URL.createObjectURL(file);
        tmp = optimistic({ type: 'image', media: preview, meta: { w, h } });
        const fd = new FormData();
        fd.append('type', 'image');
        fd.append('file', file);
        fd.append('w', w);
        fd.append('h', h);
        const r = await postJson(route('messages.send', props.active.id), fd);
        settle(tmp, { ...r.message, media: preview }); // keep the local blob — no re-download
    } catch (err) { tmp ? fail(tmp, err) : (error.value = 'Couldn’t read that image.'); }
    sending.value = false;
}

// ── voice notes ───────────────────────────────────────────
const rec = ref(null); // { recorder, stream, chunks, started, seconds, timer }
const recSeconds = ref(0);
const maxVoice = computed(() => props.limits?.max_voice_seconds || 120);
const canRecord = typeof window !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof MediaRecorder !== 'undefined';

async function startRec() {
    error.value = null;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, channelCount: 1 } });
        const mimeType = voiceMime();
        const recorder = new MediaRecorder(stream, { ...(mimeType ? { mimeType } : {}), audioBitsPerSecond: 24000 });
        const r = { recorder, stream, chunks: [], started: Date.now(), cancel: false };
        recorder.ondataavailable = (ev) => ev.data.size && r.chunks.push(ev.data);
        recorder.onstop = () => finishRec(r);
        recorder.start(1000);
        recSeconds.value = 0;
        r.timer = setInterval(() => {
            recSeconds.value = (Date.now() - r.started) / 1000;
            if (recSeconds.value >= maxVoice.value) stopRec();
        }, 200);
        rec.value = r;
    } catch (_) {
        error.value = 'Allow microphone access to record a voice note.';
    }
}
function stopRec(cancel = false) {
    const r = rec.value;
    if (!r) return;
    r.cancel = cancel;
    clearInterval(r.timer);
    if (r.recorder.state !== 'inactive') r.recorder.stop();
}
async function finishRec(r) {
    r.stream.getTracks().forEach((t) => t.stop());
    rec.value = null;
    const duration = Math.min(maxVoice.value, (Date.now() - r.started) / 1000);
    if (r.cancel || duration < 0.7 || !props.active) return;
    const type = r.recorder.mimeType || 'audio/webm';
    const blob = new Blob(r.chunks, { type });
    const ext = type.includes('ogg') ? 'ogg' : type.includes('mp4') ? 'm4a' : 'webm';
    const local = URL.createObjectURL(blob);
    const tmp = optimistic({ type: 'voice', media: local, meta: { duration } });
    try {
        const fd = new FormData();
        fd.append('type', 'voice');
        fd.append('file', new File([blob], `voice.${ext}`, { type }));
        fd.append('duration', duration.toFixed(1));
        const res = await postJson(route('messages.send', props.active.id), fd);
        settle(tmp, { ...res.message, media: local });
    } catch (e) { fail(tmp, e); }
}

// ── calls ─────────────────────────────────────────────────
function callNow() { if (props.active) startCall(props.active.id, other.value); }

// ── lifecycle ─────────────────────────────────────────────
onMounted(() => {
    document.addEventListener('visibilitychange', onVis);
    load();
    if (props.active && new URLSearchParams(window.location.search).get('call') === '1' && canCall.value) {
        callNow();
        const u = new URL(window.location.href);
        u.searchParams.delete('call');
        window.history.replaceState(window.history.state, '', u.toString());
    }
    if (props.active) nextTick(() => input.value?.focus());
});
onBeforeUnmount(() => { clearTimeout(pollTimer); document.removeEventListener('visibilitychange', onVis); if (rec.value) stopRec(true); });
watch(() => props.active?.id, () => { messages.value = []; load(); });

// ── display helpers ───────────────────────────────────────
function time(iso) { return iso ? new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : ''; }
function day(iso) {
    const d = new Date(iso);
    const today = new Date();
    const y = new Date(); y.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === y.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });
}
const rows = computed(() => {
    const out = [];
    let lastDay = null;
    messages.value.forEach((m, i) => {
        const dd = m.at ? new Date(m.at).toDateString() : lastDay;
        if (dd !== lastDay) { out.push({ divider: true, key: `d-${dd}`, label: day(m.at) }); lastDay = dd; }
        const next = messages.value[i + 1];
        out.push({ ...m, key: m.id, tail: !next || next.mine !== m.mine || next.type === 'call' });
    });
    return out;
});
const otherName = (o) => o?.store?.name || o?.name || 'neaby user';
const imgStyle = (m) => {
    const w = m.meta?.w || 4;
    const h = m.meta?.h || 3;
    return { aspectRatio: `${w} / ${h}`, width: `min(16rem, ${Math.round((w / h) * 18)}rem)` };
};
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="active ? '' : 'Messages'" wide :hide-mobile-header="!!active">
        <div class="surface -mx-3 -mt-4 grid h-[calc(100dvh-4rem-var(--safe-bottom)-3.5rem)] overflow-hidden rounded-none sm:mx-0 sm:mt-0 sm:rounded-3xl md:h-[calc(100dvh-7.5rem)] md:grid-cols-[19rem_minmax(0,1fr)]">
            <!-- Threads -->
            <aside class="min-h-0 flex-col border-r border-ink-100" :class="active ? 'hidden md:flex' : 'flex'">
                <div class="flex h-14 shrink-0 items-center px-4 font-display text-[15px] font-bold">Chats <span v-if="page.props.counts?.messages" class="ml-2 badge bg-red-100 text-red-700">{{ page.props.counts.messages }} new</span></div>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <Link
                        v-for="t in threads"
                        :key="t.id"
                        :href="route('messages.show', t.id)"
                        class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-ink-50"
                        :class="active?.id === t.id ? 'bg-brand-50/70' : ''"
                        preserve-scroll
                    >
                        <Avatar :src="t.other?.avatar" :initials="t.other?.initials || '?'" :accent="t.other?.store?.accent || '#16b26a'" :size="44" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-[14px] font-semibold text-ink-900" :class="t.unread ? 'font-bold' : ''">{{ otherName(t.other) }}</span>
                                <span class="ml-auto shrink-0 text-[11px] text-ink-400">{{ t.last?.ago }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="truncate text-[13px]" :class="t.unread ? 'font-semibold text-ink-800' : 'text-ink-500'">{{ t.last?.mine ? 'You: ' : '' }}{{ t.last?.preview || 'Say hi 👋' }}</span>
                                <span v-if="t.unread" class="ml-auto h-2.5 w-2.5 shrink-0 rounded-full bg-brand-500" />
                            </div>
                        </div>
                    </Link>
                    <EmptyState v-if="!threads.length" title="No chats yet" body="Message a seller from any product, store or profile — it all lands here." class="m-4">
                        <template #icon><MessageCircle :size="26" /></template>
                        <Link :href="route('home')" class="btn-primary">Browse neaby</Link>
                    </EmptyState>
                </div>
            </aside>

            <!-- Conversation -->
            <section v-if="active" class="flex min-h-0 flex-col">
                <header class="flex h-14 shrink-0 items-center gap-3 border-b border-ink-100 px-3 pt-safe md:px-4">
                    <Link :href="route('messages.index')" class="icon-btn -ml-1 md:hidden" aria-label="Back to chats"><ArrowLeft :size="20" /></Link>
                    <Link :href="other?.store ? route('stores.show', other.store.slug) : other?.username ? route('users.show', other.username) : '#'" class="flex min-w-0 items-center gap-2.5">
                        <Avatar :src="other?.avatar" :initials="other?.initials || '?'" :accent="other?.store?.accent || '#16b26a'" :size="36" />
                        <div class="min-w-0">
                            <div class="truncate text-[14px] font-bold leading-tight">{{ otherName(other) }}</div>
                            <div class="truncate text-[11.5px] text-ink-500">{{ other?.store ? `${other.name} · @${other.username}` : other?.username ? `@${other.username}` : '' }}</div>
                        </div>
                    </Link>
                    <div class="ml-auto flex items-center gap-1">
                        <Tip v-if="canCall" label="Voice call" side="bottom">
                            <button class="icon-btn" :disabled="call.status !== 'idle'" aria-label="Voice call" @click="callNow"><Phone :size="19" /></button>
                        </Tip>
                    </div>
                </header>

                <!-- Context (what this chat is about) -->
                <Link v-if="active.context" :href="active.context.url" class="mx-3 mt-2 flex items-center gap-3 rounded-2xl border border-ink-100 bg-ink-50/70 p-2 pr-3 text-sm transition hover:bg-ink-100 md:mx-4">
                    <img v-if="active.context.image" :src="active.context.image" alt="" class="h-10 w-10 rounded-xl object-cover" loading="lazy" />
                    <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ active.context.title }}</div><div class="truncate text-xs text-ink-500">{{ active.context.subtitle }}</div></div>
                    <span class="text-xs font-semibold text-brand-700">View</span>
                </Link>

                <div ref="list" class="min-h-0 flex-1 overflow-y-auto px-3 py-3 md:px-5" @scroll.passive="onScroll">
                    <div v-if="loading" class="flex justify-center py-10 text-ink-400"><Loader2 :size="22" class="animate-spin" /></div>
                    <div v-else-if="hasMore" class="flex justify-center pb-2"><button class="btn-sm btn-ghost" :disabled="loadingOlder" @click="older"><Loader2 v-if="loadingOlder" :size="14" class="animate-spin" /> Earlier messages</button></div>
                    <p v-if="!loading && !messages.length" class="py-10 text-center text-sm text-ink-400">Start the conversation with {{ otherName(other) }}.</p>

                    <template v-for="m in rows" :key="m.key">
                        <div v-if="m.divider" class="my-3 text-center text-[11px] font-bold uppercase tracking-[.14em] text-ink-400">{{ m.label }}</div>
                        <div v-else-if="m.type === 'call'" class="my-2 flex justify-center">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-3 py-1 text-xs text-ink-600"><component :is="/miss|declin|no answer/i.test(m.body || '') ? PhoneMissed : Phone" :size="13" /> {{ m.body || 'Voice call' }} · {{ time(m.at) }}</span>
                        </div>
                        <div v-else class="flex" :class="[m.mine ? 'justify-end' : 'justify-start', m.tail ? 'mb-2' : 'mb-0.5']">
                            <div
                                class="max-w-[78%] text-[14px] leading-snug"
                                :class="[
                                    m.type === 'image' ? 'overflow-hidden rounded-2xl' : 'rounded-2xl px-3 py-2',
                                    m.type !== 'image' && (m.mine ? 'bg-brand-500 text-white' : 'bg-ink-100 text-ink-900'),
                                    m.tail && m.type !== 'image' ? (m.mine ? 'rounded-br-md' : 'rounded-bl-md') : '',
                                    m.pending ? 'opacity-70' : '',
                                    m.failed ? 'ring-2 ring-red-300' : '',
                                ]"
                            >
                                <button v-if="m.type === 'image'" class="block bg-ink-100" :style="imgStyle(m)" aria-label="Open photo" @click="viewer = m.media">
                                    <img :src="m.media" alt="Photo" class="h-full w-full object-cover" loading="lazy" decoding="async" />
                                </button>
                                <VoiceNote v-else-if="m.type === 'voice'" :src="m.media" :duration="m.meta?.duration || 0" :mine="m.mine" />
                                <span v-else class="whitespace-pre-wrap break-words">{{ m.body }}</span>
                                <span v-if="m.type !== 'image'" class="ml-2 inline-block translate-y-0.5 text-[10px] opacity-60">{{ m.failed ? 'Not sent' : time(m.at) }}</span>
                            </div>
                        </div>
                    </template>
                    <div v-if="seen" class="-mt-1 mb-1 text-right text-[11px] font-medium text-ink-400">Seen</div>
                </div>

                <!-- Composer -->
                <div class="shrink-0 border-t border-ink-100 p-2 pb-[calc(0.5rem+var(--safe-bottom))] md:p-3">
                    <p v-if="error" class="mb-2 flex items-center gap-2 px-1 text-xs font-medium text-red-600">{{ error }} <button class="ml-auto" aria-label="Dismiss" @click="error = null"><X :size="14" /></button></p>

                    <div v-if="rec" class="flex items-center gap-3 rounded-2xl bg-red-50 px-3 py-2">
                        <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-red-500" />
                        <span class="text-sm font-semibold tabular-nums text-red-700">{{ formatClock(recSeconds) }}</span>
                        <span class="text-xs text-red-600/70">/ {{ formatClock(maxVoice) }}</span>
                        <Tip label="Discard" class="ml-auto"><button class="icon-btn text-red-600" aria-label="Discard voice note" @click="stopRec(true)"><Trash2 :size="18" /></button></Tip>
                        <Tip label="Send voice note"><button class="icon-btn-primary h-10 w-10 rounded-full" aria-label="Send voice note" @click="stopRec(false)"><Square :size="15" class="fill-current" /></button></Tip>
                    </div>
                    <form v-else class="flex items-end gap-1.5" @submit.prevent="sendText">
                        <Tip label="Photo">
                            <button type="button" class="icon-btn" :disabled="sending" aria-label="Send a photo" @click="fileInput.click()"><Loader2 v-if="sending" :size="19" class="animate-spin" /><ImagePlus v-else :size="20" /></button>
                        </Tip>
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="pickImage" />
                        <textarea
                            ref="input"
                            v-model="text"
                            rows="1"
                            maxlength="2000"
                            placeholder="Message"
                            class="max-h-32 min-h-[2.5rem] flex-1 resize-none rounded-2xl border-0 bg-ink-100/80 px-4 py-2.5 text-[14px] placeholder:text-ink-400 focus:bg-white focus:ring-2 focus:ring-brand-200"
                            @keydown.enter="onEnter"
                            @input="$event.target.style.height = 'auto'; $event.target.style.height = Math.min($event.target.scrollHeight, 128) + 'px'"
                        />
                        <Tip v-if="!text.trim() && canRecord" label="Record voice note">
                            <button type="button" class="icon-btn" aria-label="Record voice note" @click="startRec"><Mic :size="20" /></button>
                        </Tip>
                        <Tip v-else label="Send">
                            <button type="submit" class="icon-btn-primary h-10 w-10 rounded-full" :disabled="!text.trim()" aria-label="Send"><Send :size="17" /></button>
                        </Tip>
                    </form>
                </div>
            </section>
            <section v-else class="hidden flex-col items-center justify-center gap-2 p-10 text-center md:flex">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600"><MessageCircle :size="26" /></span>
                <div class="font-display text-[15px] font-bold">Your messages</div>
                <p class="max-w-xs text-sm text-ink-500">Chat, send photos and voice notes, or call — all without sharing your number.</p>
            </section>
        </div>

        <!-- Photo viewer -->
        <Teleport to="body">
            <div v-if="viewer" class="fixed inset-0 z-[65] flex items-center justify-center bg-ink-950/80 p-4 backdrop-blur-xl" @click="viewer = null">
                <img :src="viewer" alt="Photo" class="max-h-full max-w-full rounded-2xl object-contain" />
                <button class="immersive-btn absolute right-4 top-4" aria-label="Close" @click="viewer = null"><X :size="20" /></button>
            </div>
        </Teleport>
    </AppShell>
</template>
