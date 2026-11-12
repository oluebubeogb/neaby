/**
 * Shopper location for nearby-first results. Never prompts on its own: the
 * browser permission prompt only appears after the shopper taps "Near me".
 * Coords live in localStorage (+ the server session / account) so we don't ask again.
 * Denied or unavailable → nothing breaks; results just aren't distance-ranked.
 */
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { postJson, xsrf } from '@/composables/http';

const KEY = 'neaby.near';
const REFRESH_MS = 30 * 60 * 1000;

export const nearState = reactive({ busy: false, error: null });

function saved() {
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (_) { return null; }
}
function remember(v) {
    try { v ? localStorage.setItem(KEY, JSON.stringify(v)) : localStorage.removeItem(KEY); } catch (_) {}
}

function position(opts = {}) {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) return reject(new Error('unsupported'));
        navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: false, timeout: 12000, maximumAge: 10 * 60 * 1000, ...opts });
    });
}

async function send(lat, lng) {
    const area = await postJson(route('location.store'), { lat, lng });
    remember({ lat, lng, city: area?.city || null, state: area?.state || null, at: Date.now() });
    return area;
}

/** From a user gesture ("Near me"): ask the browser, save, re-rank the current page. */
export async function enableLocation() {
    if (nearState.busy) return;
    nearState.busy = true;
    nearState.error = null;
    try {
        const pos = await position();
        await send(Number(pos.coords.latitude.toFixed(5)), Number(pos.coords.longitude.toFixed(5)));
        router.reload({ reset: ['feed'] });
    } catch (e) {
        nearState.error = e?.code === 1 ? 'denied' : 'unavailable';
    } finally {
        nearState.busy = false;
    }
}

export async function disableLocation() {
    remember(null);
    try {
        await fetch(route('location.destroy'), { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' } });
    } catch (_) {}
    router.reload({ reset: ['feed'] });
}

/**
 * On page load: if this browser shared a location before but the server session
 * forgot it (new session, signed out), restore it quietly. If permission is already
 * granted, refresh the fix every 30 min — never triggers a prompt.
 */
let synced = false;
export async function syncLocation(serverNear) {
    if (synced || typeof window === 'undefined') return;
    synced = true;
    const s = saved();
    if (!s) return;
    try {
        let granted = false;
        try { granted = (await navigator.permissions?.query({ name: 'geolocation' }))?.state === 'granted'; } catch (_) {}
        if (granted && Date.now() - (s.at || 0) > REFRESH_MS) {
            const pos = await position({ timeout: 8000 });
            await send(Number(pos.coords.latitude.toFixed(5)), Number(pos.coords.longitude.toFixed(5)));
            router.reload({ reset: ['feed'] });
        } else if (!serverNear?.precise) {
            await send(s.lat, s.lng);
            router.reload({ reset: ['feed'] });
        }
    } catch (_) { /* stay quiet */ }
}
