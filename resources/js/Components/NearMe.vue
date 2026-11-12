<script setup>
import { computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Loader2, LocateFixed, MapPin, X } from 'lucide-vue-next';
import Tip from '@/Components/Tip.vue';
import TripPicker from '@/Components/TripPicker.vue';
import { disableLocation, enableLocation, nearState, syncLocation } from '@/composables/location';

/** Small "Near me" chip: one tap asks for location; when on, shows the area with a way to turn it off. On a trip it shows the trip instead. */
defineProps({ dark: Boolean });
const page = usePage();
const near = computed(() => page.props.near);
const label = computed(() => (near.value?.city ? `Near ${near.value.city}` : near.value?.state ? `Near ${near.value.state}` : 'Near you'));

onMounted(() => syncLocation(page.props.near));
</script>

<template>
    <TripPicker v-if="near?.trip" :dark="dark" />
    <div v-else-if="near?.precise" class="inline-flex items-center gap-1 rounded-full py-1 pl-3 pr-1 text-xs font-semibold" :class="dark ? 'bg-white/10 text-white ring-1 ring-white/15' : 'bg-brand-50 text-brand-800 ring-1 ring-brand-100'">
        <MapPin :size="13" />
        <span class="max-w-[9rem] truncate">{{ label }}</span>
        <Tip label="Stop using my location" side="bottom" :dark="dark">
            <button class="flex h-6 w-6 items-center justify-center rounded-full transition" :class="dark ? 'hover:bg-white/15' : 'hover:bg-brand-100'" aria-label="Stop using my location" @click="disableLocation"><X :size="13" /></button>
        </Tip>
    </div>
    <Tip v-else :label="nearState.error === 'denied' ? 'Location is blocked in your browser settings' : 'Show what’s close to you first'" side="bottom" :dark="dark">
        <button
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
            :class="dark ? 'bg-white/10 text-white ring-1 ring-white/15 hover:bg-white/20' : 'bg-white text-ink-700 shadow-soft ring-1 ring-ink-200/70 hover:text-ink-900'"
            :disabled="nearState.busy"
            @click="enableLocation"
        >
            <Loader2 v-if="nearState.busy" :size="13" class="animate-spin" />
            <LocateFixed v-else :size="13" />
            Near me
        </button>
    </Tip>
</template>
