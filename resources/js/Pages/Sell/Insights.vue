<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Download, Eye, Heart, Lock, Megaphone, QrCode, Share2, ShoppingBag, Star } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ days: Number, pro: Boolean, stats: Object, sales: Object, quality: Object, top: Array, channels: Object, sponsored: Array, seo: Object });
const max = computed(() => Math.max(1, ...(props.stats.daily || []).map((d) => d.views)));
const tiles = computed(() => [
    { label: 'Views', value: props.stats.views, icon: Eye },
    { label: 'Saves', value: props.stats.saves, icon: Heart },
    { label: 'Shares', value: props.stats.shares, icon: Share2 },
    { label: 'Share/QR visits', value: props.stats.share_visits, icon: QrCode },
    { label: 'Orders', value: props.sales.orders, icon: ShoppingBag },
    { label: 'Revenue', value: props.sales.revenue, icon: Star },
]);
const bd = computed(() => props.quality?.breakdown || {});
const bdLabels = { listing_quality: 'Listing quality', profile: 'Store profile', reliability: 'Order reliability', freshness: 'Freshness' };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Insights" :back="route('sell.dashboard')" wide>
        <template #actions>
            <a v-if="pro" :href="route('sell.insights.export', { days })" class="btn-ghost btn-sm"><Download :size="14" /> Export CSV</a>
        </template>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <Link v-for="d in [7, 30, 90]" :key="d" :href="route('sell.insights', { days: d })" class="chip" :class="days === d ? 'chip-active' : ''" preserve-scroll>{{ d }} days<Lock v-if="d === 90 && !pro" :size="12" /></Link>
            <Link v-if="!pro" :href="route('membership.index')" class="ml-auto text-sm font-semibold text-brand-700 hover:underline">Seller Pro: 90 days + export →</Link>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div v-for="t in tiles" :key="t.label" class="surface p-4"><div class="flex items-center gap-1.5 text-xs font-semibold text-ink-500"><component :is="t.icon" :size="14" /> {{ t.label }}</div><div class="mt-1 font-display text-2xl font-extrabold">{{ t.value }}</div></div>
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-[2fr_1fr]">
            <section class="surface p-5">
                <h2 class="section-title mb-3">Daily views</h2>
                <div class="flex h-40 items-end gap-[2px]">
                    <div v-for="d in stats.daily" :key="d.date" class="flex-1 rounded-t bg-brand-400/80 hover:bg-brand-600" :style="{ height: Math.max(2, (d.views / max) * 100) + '%' }" :title="`${d.date}: ${d.views}`" />
                </div>
            </section>
            <section class="surface p-5">
                <h2 class="section-title mb-1">Store quality</h2>
                <div class="font-display text-4xl font-extrabold text-brand-700">{{ quality?.score ?? '—' }}<span class="text-base text-ink-400">/100</span></div>
                <div v-for="(l, k) in bdLabels" :key="k" class="mt-2 text-sm"><div class="flex justify-between"><span>{{ l }}</span><span>{{ bd[k] ?? 0 }}</span></div><div class="mt-1 h-1.5 rounded-full bg-ink-100"><div class="h-full rounded-full bg-brand-500" :style="{ width: (bd[k] || 0) + '%' }" /></div></div>
                <p class="hint mt-3">Add 3+ photos, longer descriptions, keep stock fresh and fulfil orders to rank higher in For You.</p>
            </section>
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-[2fr_1fr]">
            <section class="surface overflow-x-auto p-5">
                <h2 class="section-title mb-3">Top products</h2>
                <table class="w-full text-sm"><thead class="text-left text-xs text-ink-500"><tr><th class="pb-2">Product</th><th>Views</th><th>Saves</th><th>Shares</th><th>Sold</th><th></th></tr></thead>
                    <tbody class="divide-y divide-ink-100"><tr v-for="p in top" :key="p.id"><td class="py-2 pr-2">{{ p.title }}</td><td>{{ p.views }}</td><td>{{ p.saves }}</td><td>{{ p.shares }}</td><td>{{ p.purchases }}</td><td><Link :href="route('sell.promote', p.id)" class="text-xs font-semibold text-brand-700">Promote</Link></td></tr></tbody>
                </table>
                <p v-if="!top.length" class="text-sm text-ink-500">No activity yet in this period.</p>
            </section>
            <section class="surface p-5 text-sm">
                <h2 class="section-title mb-3">Share channels</h2>
                <div v-for="(c, ch) in channels" :key="ch" class="flex justify-between border-b border-ink-100 py-1.5 last:border-0"><span class="capitalize">{{ String(ch).replace('_', ' ') }}</span><span class="font-semibold">{{ c }}</span></div>
                <p v-if="!Object.keys(channels || {}).length" class="text-ink-500">Share your listings to see where buyers come from.</p>
            </section>
        </div>
        <section class="surface mt-4 p-5 text-sm">
            <h2 class="section-title mb-3 flex items-center gap-2"><Megaphone :size="16" /> Sponsored placements</h2>
            <div v-for="s in sponsored" :key="s.id" class="flex flex-wrap justify-between gap-2 border-b border-ink-100 py-2 last:border-0"><span>{{ s.title }} · {{ s.days }}d · {{ s.price }}</span><span class="text-ink-500"><span class="capitalize">{{ s.status }}</span><template v-if="s.ends"> · ends {{ s.ends }}</template> · {{ s.impressions }} views · {{ s.clicks }} clicks</span></div>
            <p v-if="!sponsored.length" class="text-ink-500">None yet. Promote a listing to appear (clearly labelled) in the For You feed.</p>
        </section>
    </AppShell>
</template>
