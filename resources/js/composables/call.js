/**
 * 1:1 WebRTC voice calls with HTTP-polled signalling (no websocket server).
 * One global call at a time; the CallOverlay in AppShell renders this state.
 *
 * Data-responsible defaults: mono Opus capped at ~24 kbps with DTX (silence
 * suppression), echo cancellation + noise suppression on, polling slows to
 * every 3s once connected and stops when the call ends.
 */
import { reactive } from 'vue';
import { getJson, postJson } from '@/composables/http';

export const call = reactive({
    id: null,
    status: 'idle', // idle | outgoing | incoming | connecting | active | ended
    peer: null,
    conversationId: null,
    caller: false,
    muted: false,
    seconds: 0,
    error: null,
    note: null,
});

let pc = null;
let local = null;
let audio = null;
let pollTimer = null;
let tick = null;
let lastSignal = 0;
let pendingIce = [];
let remoteSet = false;
let ice = [];

const MAX_BITRATE = 24000;

export function configureIce(servers) {
    ice = Array.isArray(servers) ? servers : [];
}

function reset(note = null) {
    clearTimeout(pollTimer);
    clearInterval(tick);
    pollTimer = null;
    tick = null;
    try { pc?.close(); } catch (_) {}
    pc = null;
    local?.getTracks().forEach((t) => t.stop());
    local = null;
    if (audio) { audio.srcObject = null; audio = null; }
    lastSignal = 0;
    pendingIce = [];
    remoteSet = false;
    Object.assign(call, { status: note ? 'ended' : 'idle', muted: false, note });
    if (note) setTimeout(() => { if (call.status === 'ended') Object.assign(call, { status: 'idle', id: null, peer: null, note: null, seconds: 0 }); }, 2500);
}

/** Opus: mono, ~24 kbps, DTX on, FEC on. */
function tuneSdp(sdp) {
    const m = sdp.match(/a=rtpmap:(\d+) opus\/48000/i);
    if (!m) return sdp;
    const pt = m[1];
    const params = `maxaveragebitrate=${MAX_BITRATE};stereo=0;sprop-stereo=0;useinbandfec=1;usedtx=1`;
    const fmtp = new RegExp(`a=fmtp:${pt} ([^\\r\\n]*)`);
    if (fmtp.test(sdp)) return sdp.replace(fmtp, (_, p) => `a=fmtp:${pt} ${p.replace(/;?(maxaveragebitrate|stereo|sprop-stereo|useinbandfec|usedtx)=[^;]*/g, '')};${params}`.replace(' ;', ' '));
    return sdp.replace(m[0], `${m[0]}\r\na=fmtp:${pt} ${params}`);
}

async function capBitrate() {
    for (const s of pc?.getSenders() || []) {
        if (s.track?.kind !== 'audio') continue;
        try {
            const p = s.getParameters();
            if (!p.encodings?.length) p.encodings = [{}];
            p.encodings[0].maxBitrate = MAX_BITRATE;
            await s.setParameters(p);
        } catch (_) { /* not supported everywhere */ }
    }
}

async function getMic() {
    if (!navigator.mediaDevices?.getUserMedia) throw new Error('This browser can’t make calls.');
    return navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 }, video: false });
}

function makePeer() {
    pc = new RTCPeerConnection({ iceServers: ice });
    local.getTracks().forEach((t) => pc.addTrack(t, local));
    pc.onicecandidate = (e) => { if (e.candidate) send('ice', JSON.stringify(e.candidate)); };
    pc.ontrack = (e) => {
        if (!audio) { audio = new Audio(); audio.autoplay = true; }
        audio.srcObject = e.streams[0];
        audio.play().catch(() => {});
    };
    pc.onconnectionstatechange = () => {
        if (pc?.connectionState === 'connected') {
            call.status = 'active';
            capBitrate();
            if (!tick) { call.seconds = 0; tick = setInterval(() => call.seconds++, 1000); }
        } else if (pc && ['failed'].includes(pc.connectionState)) {
            call.error = 'Couldn’t connect the call (network). A TURN relay may be needed on this network.';
            hangup();
        }
    };
}

function send(type, payload) {
    if (!call.id) return Promise.resolve();
    return postJson(route('calls.signal', call.id), { type, payload }).catch(() => {});
}

async function onSignal(s) {
    const data = JSON.parse(s.payload);
    if (s.type === 'offer' && !call.caller) {
        await pc.setRemoteDescription(data);
        remoteSet = true;
        const ans = await pc.createAnswer();
        ans.sdp = tuneSdp(ans.sdp);
        await pc.setLocalDescription(ans);
        await send('answer', JSON.stringify(pc.localDescription));
        call.status = 'connecting';
    } else if (s.type === 'answer' && call.caller) {
        await pc.setRemoteDescription(data);
        remoteSet = true;
        call.status = 'connecting';
    } else if (s.type === 'ice') {
        if (remoteSet) await pc.addIceCandidate(data).catch(() => {});
        else pendingIce.push(data);
        return;
    }
    if (remoteSet && pendingIce.length) {
        for (const c of pendingIce.splice(0)) await pc.addIceCandidate(c).catch(() => {});
    }
}

async function poll() {
    if (!call.id || ['idle', 'ended'].includes(call.status)) return;
    try {
        const res = await getJson(route('calls.signals', { call: call.id, after: lastSignal }));
        for (const s of res.signals || []) {
            lastSignal = Math.max(lastSignal, s.id);
            if (pc) await onSignal(s);
        }
        const st = res.call?.status;
        if (['ended', 'missed', 'declined'].includes(st)) {
            return reset({ ended: 'Call ended', missed: call.caller ? 'No answer' : 'Missed call', declined: 'Call declined' }[st]);
        }
    } catch (_) { /* transient */ }
    pollTimer = setTimeout(poll, call.status === 'active' ? 3000 : 1000);
}

export async function startCall(conversationId, peer = null) {
    if (call.status !== 'idle' && call.status !== 'ended') return;
    call.error = null;
    try {
        local = await getMic();
    } catch (e) {
        call.error = e.message?.includes('can’t') ? e.message : 'Allow microphone access to call.';
        return;
    }
    try {
        const res = await postJson(route('calls.start', conversationId));
        Object.assign(call, { id: res.call.id, peer: res.call.peer || peer, conversationId, caller: true, status: 'outgoing', seconds: 0, note: null });
        makePeer();
        const offer = await pc.createOffer({ offerToReceiveAudio: true });
        offer.sdp = tuneSdp(offer.sdp);
        await pc.setLocalDescription(offer);
        await send('offer', JSON.stringify(pc.localDescription));
        poll();
    } catch (e) {
        call.error = e.message;
        reset();
    }
}

/** From the pulse: someone is ringing us. */
export function ringing(info) {
    if (call.status !== 'idle' || !info) return;
    Object.assign(call, { id: info.id, peer: info.from, conversationId: info.conversation_id, caller: false, status: 'incoming', error: null, note: null, seconds: 0 });
    // Watch for the caller hanging up before we answer.
    pollTimer = setTimeout(function watch() {
        if (call.status !== 'incoming') return;
        getJson(route('calls.show', call.id)).then((r) => {
            if (call.status !== 'incoming') return;
            if (r.call?.status !== 'ringing') reset('Missed call');
            else pollTimer = setTimeout(watch, 2500);
        }).catch(() => { pollTimer = setTimeout(watch, 4000); });
    }, 2500);
}

export async function answer() {
    if (call.status !== 'incoming') return;
    clearTimeout(pollTimer);
    try {
        local = await getMic();
    } catch (_) {
        call.error = 'Allow microphone access to answer.';
        return decline();
    }
    try {
        const r = await postJson(route('calls.answer', call.id));
        if (r.call?.status !== 'active') return reset('Call ended');
        call.status = 'connecting';
        makePeer();
        poll();
    } catch (e) {
        call.error = e.message;
        reset();
    }
}

export async function decline() {
    const id = call.id;
    reset();
    if (id) postJson(route('calls.decline', id)).catch(() => {});
}

export async function hangup() {
    const id = call.id;
    reset('Call ended');
    if (id) postJson(route('calls.end', id)).catch(() => {});
}

export function toggleMute() {
    call.muted = !call.muted;
    local?.getAudioTracks().forEach((t) => (t.enabled = !call.muted));
}

if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', () => {
        if (call.id && !['idle', 'ended'].includes(call.status) && navigator.sendBeacon) {
            const fd = new FormData();
            // sendBeacon can't set headers; Laravel accepts _token from the form body.
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token) fd.append('_token', token);
            navigator.sendBeacon(route('calls.end', call.id), fd);
        }
    });
}
