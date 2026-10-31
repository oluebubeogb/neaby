<script setup>
/**
 * Global call surface + inbox pulse. Mounted once in AppShell for signed-in users.
 * Pulse is a tiny JSON poll (unread count + any ringing call) that pauses while
 * the tab is hidden, so idle tabs cost nothing.
 */
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Mic, MicOff, Phone, PhoneOff } from 'lucide-vue-next';
import Avatar from '@/Components/Avatar.vue';
import { call, answer, configureIce, decline, hangup, ringing, toggleMute } from '@/composables/call';
import { getJson } from '@/composables/http';

const page = usePage();
const rtc = computed(() => page.props.rtc || {});
const user = computed(() => page.props.auth?.user);
let timer = null;
let ringTone = null;

watch(() => rtc.value.ice, (v) => configureIce(v), { immediate: true });

async function pulse() {
    clearTimeout(timer);
    if (!user.value || !rtc.value.messaging) return;
    if (document.visibilityState === 'visible') {
        try {
            const r = await getJson(route('messages.pulse'));
            if (page.props.counts) page.props.counts.messages = r.unread;
            if (r.call && rtc.value.calls) ringing(r.call);
        } catch (_) { /* offline */ }
    }
    const fast = call.status === 'incoming' || call.status === 'outgoing';
    timer = setTimeout(pulse, (fast ? 5 : rtc.value.pulse_seconds || 10) * 1000);
}
function onVis() { if (document.visibilityState === 'visible') pulse(); }

/** A soft two-tone ring generated in WebAudio (no audio asset download). */
function startTone() {
    stopTone();
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const gain = ctx.createGain();
        gain.gain.value = 0;
        gain.connect(ctx.destination);
        const osc = ctx.createOscillator();
        osc.frequency.value = 520;
        osc.connect(gain);
        osc.start();
        let on = false;
        const id = setInterval(() => { on = !on; gain.gain.setTargetAtTime(on ? 0.06 : 0, ctx.currentTime, 0.02); osc.frequency.value = on ? 520 : 660; }, 600);
        ringTone = { ctx, id };
    } catch (_) {}
}
function stopTone() {
    if (!ringTone) return;
    clearInterval(ringTone.id);
    ringTone.ctx.close().catch(() => {});
    ringTone = null;
}
watch(() => call.status, (s) => (s === 'incoming' ? startTone() : stopTone()));

onMounted(() => { pulse(); document.addEventListener('visibilitychange', onVis); });
onBeforeUnmount(() => { clearTimeout(timer); stopTone(); document.removeEventListener('visibilitychange', onVis); });

const visible = computed(() => call.status !== 'idle' || call.error);
const label = computed(() => ({
    outgoing: 'Calling…',
    incoming: 'Incoming voice call',
    connecting: 'Connecting…',
    active: clock(call.seconds),
    ended: call.note || 'Call ended',
}[call.status] || ''));
function clock(s) { const m = Math.floor(s / 60); return `${m}:${String(s % 60).padStart(2, '0')}`; }
function openThread() { if (call.conversationId) router.visit(route('messages.show', call.conversationId)); }
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-3" leave-active-class="transition duration-150" leave-to-class="opacity-0 translate-y-3">
            <div v-if="visible" class="fixed inset-x-3 bottom-[calc(5.5rem+var(--safe-bottom))] z-[70] mx-auto max-w-sm md:bottom-6 md:right-6 md:left-auto md:mx-0 md:w-[22rem]" role="dialog" aria-live="polite" aria-label="Voice call">
                <div class="overflow-hidden rounded-3xl bg-ink-900/85 p-4 text-white shadow-2xl ring-1 ring-white/10 backdrop-blur-xl">
                    <div v-if="call.error && call.status === 'idle'" class="flex items-start gap-3 text-sm">
                        <PhoneOff :size="18" class="mt-0.5 shrink-0 text-red-300" />
                        <p class="flex-1">{{ call.error }}</p>
                        <button class="text-xs font-semibold text-white/70 hover:text-white" @click="call.error = null">Dismiss</button>
                    </div>
                    <template v-else>
                        <div class="flex items-center gap-3">
                            <button class="relative shrink-0" aria-label="Open conversation" @click="openThread">
                                <span v-if="['incoming', 'outgoing'].includes(call.status)" class="absolute inset-0 animate-ping rounded-full bg-brand-400/40" />
                                <Avatar :src="call.peer?.avatar" :initials="call.peer?.initials || '?'" :size="46" class="relative ring-white/20" />
                            </button>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[15px] font-bold">{{ call.peer?.store?.name || call.peer?.name || 'neaby user' }}</div>
                                <div class="text-xs text-white/70" :class="call.status === 'active' ? 'tabular-nums text-brand-300' : ''">{{ label }}</div>
                            </div>
                            <template v-if="call.status === 'incoming'">
                                <button class="flex h-11 w-11 items-center justify-center rounded-full bg-red-500 transition hover:bg-red-600 active:scale-95" aria-label="Decline" @click="decline"><PhoneOff :size="19" /></button>
                                <button class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-500 transition hover:bg-brand-600 active:scale-95" aria-label="Answer" @click="answer"><Phone :size="19" /></button>
                            </template>
                            <template v-else-if="call.status !== 'ended'">
                                <button class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 transition hover:bg-white/25" :aria-label="call.muted ? 'Unmute' : 'Mute'" :aria-pressed="call.muted" @click="toggleMute"><MicOff v-if="call.muted" :size="19" /><Mic v-else :size="19" /></button>
                                <button class="flex h-11 w-11 items-center justify-center rounded-full bg-red-500 transition hover:bg-red-600 active:scale-95" aria-label="Hang up" @click="hangup"><PhoneOff :size="19" /></button>
                            </template>
                        </div>
                        <p v-if="call.error" class="mt-2 text-xs text-red-200">{{ call.error }}</p>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
