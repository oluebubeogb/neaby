<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Check, Crown, Repeat2, Store, TrendingUp } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import IconCircle from '@/Components/IconCircle.vue';

const props = defineProps({ plans: Object, current: Object, entitlements: Array, history: Array, hasStore: Boolean, sellerProEnabled: Boolean, seo: Object });
const meta = {
    reseller: { title: 'Reseller', blurb: 'Resell products from verified suppliers. See supplier prices, publish your own offers and earn a margin — stock stays with the supplier.', icon: Repeat2, color: '#7c3aed' },
    supplier_resale: { title: 'Supplier Resale', blurb: 'Let resellers sell your stock. Set a supplier price and terms; every sale draws from your one inventory.', icon: Store, color: '#16b26a', needsStore: true },
    seller_pro: { title: 'Seller Pro', blurb: '90-day insights, CSV export and pro tools for your store.', icon: TrendingUp, color: '#0ea5e9', needsStore: true },
};
const types = computed(() => Object.keys(meta).filter((t) => props.plans[t]?.length && (t !== 'seller_pro' || props.sellerProEnabled)));
const busy = ref(null);
const buy = (p) => { busy.value = p.code; router.post(route('membership.purchase', p.code), {}, { onFinish: () => (busy.value = null) }); };
const cancel = (s) => confirm('Cancel? You keep access until the end of the current period, and accepted supplier commitments are honoured.') && router.post(route('membership.cancel', s.id), {}, { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Membership">
        <div class="mx-auto grid max-w-5xl gap-5">
            <section v-for="t in types" :key="t" class="surface p-5 md:p-6">
                <div class="flex flex-wrap items-start gap-4">
                    <IconCircle :color="meta[t].color" :size="48"><component :is="meta[t].icon" :size="22" /></IconCircle>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-lg font-extrabold">{{ meta[t].title }}</h2>
                        <p class="mt-1 max-w-xl text-sm text-ink-500">{{ meta[t].blurb }}</p>
                    </div>
                    <div v-if="current[t]" class="rounded-2xl bg-brand-50 px-4 py-2 text-sm">
                        <div class="flex items-center gap-1.5 font-semibold text-brand-800"><Crown :size="14" /> {{ current[t].plan }} · <span class="capitalize">{{ current[t].status }}</span></div>
                        <div class="text-ink-600">{{ current[t].cancelled ? 'Access until' : 'Renews/ends' }} {{ current[t].ends_at }} ({{ current[t].days_left }} days)</div>
                        <button v-if="!current[t].cancelled" class="mt-1 text-xs font-semibold text-red-600 hover:underline" @click="cancel(current[t])">Cancel</button>
                    </div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div v-for="p in plans[t]" :key="p.code" class="rounded-2xl border border-ink-200 p-4">
                        <div class="flex items-baseline justify-between"><span class="font-semibold">{{ p.name }}</span><span class="font-display text-xl font-extrabold">{{ p.price }}<span class="text-sm font-medium text-ink-500">/{{ p.interval === 'yearly' ? 'yr' : 'mo' }}</span></span></div>
                        <ul class="mt-3 grid gap-1 text-sm text-ink-600"><li v-for="f in p.features" :key="f" class="flex gap-2"><Check :size="15" class="mt-0.5 shrink-0 text-brand-600" /> {{ f }}</li></ul>
                        <button class="btn-primary mt-4 w-full" :disabled="busy === p.code || (meta[t].needsStore && !hasStore)" @click="buy(p)">{{ current[t] && current[t].plan_code === p.code ? 'Renew / extend' : current[t] ? 'Switch / extend' : 'Join' }}</button>
                        <p v-if="meta[t].needsStore && !hasStore" class="hint mt-1">Create a store first.</p>
                    </div>
                </div>
            </section>
            <section v-if="history.length" class="surface p-5 text-sm">
                <h2 class="section-title mb-2">History</h2>
                <div v-for="h in history" :key="h.id" class="flex justify-between border-b border-ink-100 py-2 last:border-0"><span>{{ h.plan }}</span><span class="capitalize text-ink-500">{{ h.status }} · {{ h.ends_at }}</span></div>
            </section>
        </div>
    </AppShell>
</template>
