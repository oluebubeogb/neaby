<script setup>
/**
 * "Pin my shop's exact location": turns on GPS, shows the fix on a map (free
 * OpenStreetMap embed, no API key), and only counts once the owner confirms.
 * v-model = { lat, lng, accuracy, confirmed }. Emits `area` ({ city, state }) from
 * an offline nearest-town lookup so empty city/state fields can be pre-filled.
 */
import { computed, ref } from 'vue';
import { Check, Loader2, LocateFixed, MapPin, RotateCcw, Trash2 } from 'lucide-vue-next';
import Tip from '@/Components/Tip.vue';
import { getJson } from '@/composables/http';

const model = defineModel({ type: Object, required: true });
const emit = defineEmits(['area', 'clear']);

const busy = ref(false);
const error = ref(null); // 'denied' | 'unavailable'
const draft = ref(null); // { lat, lng, accuracy } awaiting confirmation

const point = computed(() => draft.value || (model.value.lat != null ? model.value : null));
const mapSrc = computed(() => {
    const p = point.value;
    if (!p) return null;
    const d = 0.004; // ~450 m each way
    const bbox = [p.lng - d, p.lat - d * 0.7, p.lng + d, p.lat + d * 0.7].map((n) => n.toFixed(6)).join(',');
    return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${p.lat.toFixed(6)},${p.lng.toFixed(6)}`;
});

/** Best fix within ~10 s: watch until accuracy ≤ 25 m or time runs out. */
function locate() {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) return reject({ code: 2 });
        let best = null;
        const done = () => { navigator.geolocation.clearWatch(id); clearTimeout(t); best ? resolve(best) : reject({ code: 3 }); };
        const id = navigator.geolocation.watchPosition(
            (pos) => {
                if (!best || pos.coords.accuracy < best.coords.accuracy) best = pos;
                if (pos.coords.accuracy <= 25) done();
            },
            (e) => { navigator.geolocation.clearWatch(id); clearTimeout(t); best ? resolve(best) : reject(e); },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
        const t = setTimeout(done, 10000);
    });
}

async function pin() {
    busy.value = true;
    error.value = null;
    try {
        const pos = await locate();
        draft.value = {
            lat: Number(pos.coords.latitude.toFixed(6)),
            lng: Number(pos.coords.longitude.toFixed(6)),
            accuracy: Math.round(pos.coords.accuracy || 0),
        };
    } catch (e) {
        error.value = e?.code === 1 ? 'denied' : 'unavailable';
    } finally {
        busy.value = false;
    }
}

async function confirm() {
    if (!draft.value) return;
    model.value = { ...draft.value, confirmed: true, cleared: false };
    const p = draft.value;
    draft.value = null;
    try { emit('area', await getJson(route('location.area', { lat: p.lat, lng: p.lng }))); } catch (_) { /* optional */ }
}

function remove() {
    draft.value = null;
    model.value = { lat: null, lng: null, accuracy: null, confirmed: false, cleared: true };
    emit('clear');
}
</script>

<template>
    <div>
        <div v-if="point" class="overflow-hidden rounded-2xl ring-1 ring-ink-200/70">
            <iframe :src="mapSrc" title="Shop location" class="pointer-events-none block h-48 w-full border-0 md:h-56" loading="lazy" referrerpolicy="no-referrer" />
            <div class="flex flex-wrap items-center gap-2 bg-white px-3 py-2.5 text-sm">
                <MapPin :size="15" class="shrink-0 text-brand-600" />
                <span class="tabular-nums text-ink-600">{{ point.lat.toFixed(5) }}, {{ point.lng.toFixed(5) }}</span>
                <span v-if="point.accuracy" class="text-xs text-ink-400">±{{ point.accuracy }} m</span>
                <div class="ml-auto flex items-center gap-1.5">
                    <template v-if="draft">
                        <Tip label="Try again"><button type="button" class="icon-btn h-9 w-9" aria-label="Try again" :disabled="busy" @click="pin"><Loader2 v-if="busy" :size="16" class="animate-spin" /><RotateCcw v-else :size="16" /></button></Tip>
                        <button type="button" class="btn-primary btn-sm rounded-full px-4" @click="confirm"><Check :size="15" /> Confirm</button>
                    </template>
                    <template v-else>
                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700"><Check :size="13" /> Pinned</span>
                        <Tip label="Re-pin"><button type="button" class="icon-btn h-9 w-9" aria-label="Re-pin" :disabled="busy" @click="pin"><Loader2 v-if="busy" :size="16" class="animate-spin" /><LocateFixed v-else :size="16" /></button></Tip>
                        <Tip label="Remove pin"><button type="button" class="icon-btn h-9 w-9 text-red-600" aria-label="Remove pin" @click="remove"><Trash2 :size="16" /></button></Tip>
                    </template>
                </div>
            </div>
        </div>

        <button v-else type="button" class="btn-ghost w-full justify-center rounded-2xl py-3" :disabled="busy" @click="pin">
            <Loader2 v-if="busy" :size="17" class="animate-spin" />
            <LocateFixed v-else :size="17" />
            Pin my shop’s exact location
        </button>
        <p v-if="error" class="mt-2 text-xs font-medium text-red-600">{{ error === 'denied' ? 'Location is blocked for this site in your browser settings.' : 'Couldn’t get a GPS fix. Try again.' }}</p>
    </div>
</template>
