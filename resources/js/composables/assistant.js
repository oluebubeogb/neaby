/**
 * Shopper assistant UI state shared by AppShell, Search and the voice panel.
 * Nothing here runs by itself: the voice panel only opens on a tap, and no
 * request is made until the shopper speaks/types, snaps a photo or submits.
 */
import { reactive } from 'vue';
import { postJson } from '@/composables/http';
import { compressImage } from '@/composables/media';

export const assistant = reactive({ voiceOpen: false });

export function openVoice() { assistant.voiceOpen = true; }
export function closeVoice() { assistant.voiceOpen = false; }

/** Shrink on the device (CLIP only needs ~224 px), then POST /search/photo. */
export async function photoSearch(file, { underBalance = false } = {}) {
    let upload = file;
    try { upload = (await compressImage(file, { max: 640, quality: 0.8 })).file; } catch (_) { /* send the original */ }
    const fd = new FormData();
    fd.append('photo', upload);
    if (underBalance) fd.append('under_balance', '1');
    return postJson(route('search.photo'), fd);
}

/** Browser speech (Web Speech API). Null when unsupported → typed fallback. */
export function speechRecognition() {
    if (typeof window === 'undefined') return null;
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) return null;
    const r = new SR();
    r.lang = 'en-NG';
    r.interimResults = true;
    r.continuous = false;
    r.maxAlternatives = 1;
    return r;
}

export function speak(text, enabled = true) {
    if (!enabled || !text || typeof window === 'undefined' || !window.speechSynthesis) return;
    try {
        window.speechSynthesis.cancel();
        const u = new SpeechSynthesisUtterance(text);
        u.lang = 'en-NG';
        u.rate = 1.02;
        window.speechSynthesis.speak(u);
    } catch (_) { /* silent */ }
}

export function stopSpeaking() {
    try { window.speechSynthesis?.cancel(); } catch (_) {}
}
