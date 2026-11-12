<script setup>
/**
 * Trip-aware shopping: pick where you're going and results rank around that
 * city instead of where you are now. Clearing it restores normal "near me".
 */
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Loader2, Plane, X } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import { getJson, postJson, xsrf } from '@/composables/http';

defineProps({ dark: Boolean });
const page = usePage();
const trip = computed(() => (page.props.near?.trip ? page.props.near : null));
const open = ref(false);
const city = ref('');
const cities = ref([]);
const busy = ref(false);
const error = ref(null);

async function show() {
    open.value = true;
    error.value = null;
    if (!cities.value.length) {
        try { cities.value = (await getJson(route('trip.cities'))).cities || []; } catch (_) {}
    }
}
async function save() {
    if (!city.value.trim()) return;
    busy.value = true;
    error.value = null;
    try {
        await postJson(route('trip.store'), { city: city.value.trim() });
        open.value = false;
        router.reload({ reset: ['feed'] });
    } catch (e) {
        error.value = e.message || 'Pick a city from the list.';
    } finally {
        busy.value = false;
    }
}
async function clear() {
    try {
        await fetch(route('trip.destroy'), { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' } });
    } catch (_) {}
    router.reload({ reset: ['feed'] });
}
</script>

<template>
    <div v-if="trip" class="inline-flex items-center gap-1 rounded-full py-1 pl-3 pr-1 text-xs font-semibold" :class="dark ? 'bg-white/10 text-white ring-1 ring-white/15' : 'bg-sky-50 text-sky-800 ring-1 ring-sky-100'">
        <Plane :size="13" />
        <span class="max-w-[9rem] truncate">Trip: {{ trip.city || trip.state }}</span>
        <button class="flex h-6 w-6 items-center justify-center rounded-full transition hover:bg-sky-100" aria-label="End trip" title="End trip — back to near me" @click="clear"><X :size="13" /></button>
    </div>
    <button v-else type="button" class="chip" @click="show"><Plane :size="15" class="text-sky-600" /> Trip</button>

    <Modal :show="open" max-width="md" @close="open = false">
        <form class="p-5" @submit.prevent="save">
            <h2 class="font-display text-lg font-bold text-ink-900">Shopping for a trip?</h2>
            <p class="mt-1 text-sm text-ink-500">We’ll show what’s in stock around your destination first. You can end it any time.</p>
            <label class="label mt-4" for="trip-city">Where are you going?</label>
            <input id="trip-city" v-model="city" list="trip-cities" class="input" placeholder="e.g. Abuja, Port Harcourt" autocomplete="off" autofocus />
            <datalist id="trip-cities"><option v-for="c in cities" :key="c" :value="c" /></datalist>
            <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn-ghost" @click="open = false">Cancel</button>
                <button class="btn-primary" :disabled="busy || !city.trim()"><Loader2 v-if="busy" :size="16" class="animate-spin" /> Shop there</button>
            </div>
        </form>
    </Modal>
</template>
