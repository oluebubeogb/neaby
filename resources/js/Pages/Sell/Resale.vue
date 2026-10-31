<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { ImageOff, Lock, Repeat2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ canManage: Boolean, live: Boolean, products: Array, resellers: Array, analytics: Object, seo: Object });
const edits = reactive(Object.fromEntries(props.products.map((p) => [p.id, { resale_enabled: p.resale_enabled, resale_price: p.resale_price, resale_min_price: p.resale_min_price, resale_terms: p.resale_terms || '', resale_fulfilment: p.resale_fulfilment }])));
const save = (p) => router.post(route('sell.resale.update', p.id), { ...edits[p.id], resale_enabled: edits[p.id].resale_enabled ? 1 : 0 }, { preserveScroll: true });
const rel = (r, status) => router.post(route('sell.resale.relationship', r.id), { status }, { preserveScroll: true });
const margin = (p) => { const s = Number(edits[p.id].resale_price); return s ? Math.round(p.price_minor / 100 - s) : null; };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Supplier resale" :back="route('sell.dashboard')" wide>
        <div v-if="!canManage" class="surface mb-4 flex flex-wrap items-center gap-3 border border-violet-200 p-4">
            <Lock :size="18" class="text-violet-700" />
            <div class="flex-1 text-sm"><b>Supplier Resale membership needed</b> to let resellers sell your stock.<span v-if="live"> You're in your grace period — existing offers stay live, but you can't enable new products.</span></div>
            <Link :href="route('membership.index')" class="btn-dark btn-sm">See plans</Link>
        </div>
        <div v-if="analytics" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="surface p-4"><div class="text-xs text-ink-500">Reseller offers</div><div class="font-display text-2xl font-extrabold">{{ analytics.offers }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Offer views (30d)</div><div class="font-display text-2xl font-extrabold">{{ analytics.reseller_views_30d }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Enquiries (30d)</div><div class="font-display text-2xl font-extrabold">{{ analytics.reseller_enquiries_30d }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Open requests</div><div class="font-display text-2xl font-extrabold">{{ analytics.open_requests }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Accepted</div><div class="font-display text-2xl font-extrabold">{{ analytics.accepted_requests }}</div></div>
        </div>
        <div class="mb-3 flex gap-2"><Link :href="route('requests.index', { tab: 'incoming' })" class="btn-soft btn-sm"><Repeat2 :size="14" /> Reseller requests</Link></div>
        <div class="grid gap-3">
            <div v-for="p in products" :key="p.id" class="surface grid gap-3 p-4 md:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))_auto] md:items-end">
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-ink-100"><img v-if="p.image" :src="p.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="14" /></div></div>
                    <div class="min-w-0 text-sm"><div class="truncate font-medium">{{ p.title }}</div><div class="text-ink-500">Retail {{ p.price }} · {{ p.available }} avail · {{ p.offers }} offers</div>
                        <label class="mt-1 inline-flex items-center gap-2 text-xs font-semibold"><input v-model="edits[p.id].resale_enabled" type="checkbox" class="rounded text-violet-600" :disabled="!canManage && !p.resale_enabled" /> Open to resellers</label></div>
                </div>
                <div><label class="label">Supplier price ₦</label><input v-model.number="edits[p.id].resale_price" type="number" min="1" class="input py-1.5" /><div v-if="margin(p) !== null" class="text-[11px]" :class="margin(p) < 0 ? 'text-red-600' : 'text-ink-400'">Reseller margin at retail ₦{{ margin(p).toLocaleString() }}</div></div>
                <div><label class="label">Min resale ₦</label><input v-model.number="edits[p.id].resale_min_price" type="number" min="0" class="input py-1.5" /></div>
                <div><label class="label">Fulfilment</label><select v-model="edits[p.id].resale_fulfilment" class="input py-1.5"><option value="both">Both</option><option value="reseller_collects">Reseller collects</option><option value="supplier_dispatches">I dispatch</option></select></div>
                <button class="btn-dark btn-sm" @click="save(p)">Save</button>
                <textarea v-model="edits[p.id].resale_terms" rows="1" class="input md:col-span-5" placeholder="Terms for resellers (payment, returns, pickup address…)" />
            </div>
        </div>
        <section v-if="resellers.length" class="surface mt-4 p-5 text-sm">
            <h2 class="section-title mb-2">Resellers</h2>
            <div v-for="r in resellers" :key="r.id" class="flex items-center justify-between border-b border-ink-100 py-2 last:border-0">
                <span>{{ r.name }} <span class="text-ink-500">@{{ r.username }}</span></span>
                <span class="flex items-center gap-2"><span class="badge capitalize" :class="r.status === 'blocked' ? 'bg-red-100 text-red-700' : 'bg-brand-100 text-brand-800'">{{ r.status }}</span>
                    <button v-if="r.status !== 'blocked'" class="text-xs font-semibold text-red-600" @click="rel(r, 'blocked')">Block</button>
                    <button v-else class="text-xs font-semibold text-brand-700" @click="rel(r, 'active')">Unblock</button></span>
            </div>
        </section>
    </AppShell>
</template>
