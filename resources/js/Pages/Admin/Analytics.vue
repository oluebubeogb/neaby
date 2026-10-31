<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ days: Number, events: Object, series: Array, commerce: Object, memberships: Object, membershipRevenue: String, resale: Object, sponsored: Object, seo: Object });
const max = computed(() => Math.max(1, ...props.series.map((s) => s.view || 0)));
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Analytics" :back="route('admin.index')" wide>
        <div class="mb-4 flex gap-2"><Link v-for="d in [7, 30, 90]" :key="d" :href="route('admin.analytics', { days: d })" class="chip" :class="days === d ? 'chip-active' : ''">{{ d }} days</Link></div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="surface p-4"><div class="text-xs text-ink-500">Paid orders</div><div class="font-display text-xl font-extrabold">{{ commerce.orders }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">GMV</div><div class="font-display text-xl font-extrabold">{{ commerce.gmv }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Membership revenue</div><div class="font-display text-xl font-extrabold">{{ membershipRevenue }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Sponsored revenue</div><div class="font-display text-xl font-extrabold">{{ sponsored.revenue }}</div><div class="text-xs text-ink-400">{{ sponsored.live }} live</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Refunds (pending)</div><div class="font-display text-xl font-extrabold">{{ commerce.refunds }} <span class="text-base text-amber-600">({{ commerce.pending_refunds }})</span></div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Active reseller offers</div><div class="font-display text-xl font-extrabold">{{ resale.offers }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Supplier requests open / accepted</div><div class="font-display text-xl font-extrabold">{{ resale.requests_open }} / {{ resale.requests_accepted }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Active memberships</div><div class="text-sm"><div v-for="(c, t) in memberships" :key="t" class="capitalize">{{ String(t).replace('_', ' ') }}: <b>{{ c }}</b></div><span v-if="!Object.keys(memberships).length">0</span></div></div>
        </div>
        <section class="surface mt-4 p-5">
            <h2 class="section-title mb-3">Daily views</h2>
            <div class="flex h-40 items-end gap-[2px]"><div v-for="s in series" :key="s.date" class="flex-1 rounded-t bg-brand-400/80" :style="{ height: Math.max(2, ((s.view || 0) / max) * 100) + '%' }" :title="`${s.date}: ${s.view || 0} views, ${s.share || 0} shares, ${s.save || 0} saves, ${s.purchase || 0} purchases`" /></div>
        </section>
        <section class="surface mt-4 p-5 text-sm">
            <h2 class="section-title mb-2">Events</h2>
            <div class="grid grid-cols-2 gap-x-6 md:grid-cols-4"><div v-for="(c, t) in events" :key="t" class="flex justify-between border-b border-ink-100 py-1.5"><span class="capitalize">{{ String(t).replace('_', ' ') }}</span><b>{{ c }}</b></div></div>
        </section>
    </AppShell>
</template>
