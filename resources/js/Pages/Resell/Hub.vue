<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Eye, ImageOff, Inbox, MessageCircle, Repeat2, Search, Share2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';

const props = defineProps({ products: Array, next_page_url: String, prev_page_url: String, q: String, stats: Object, seo: Object });
const search = ref(props.q || '');
const go = () => router.get(route('resell.index'), search.value ? { q: search.value } : {});
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Resell" wide>
        <template #actions>
            <Link :href="route('resell.offers.index')" class="btn-ghost btn-sm">My offers</Link>
            <Link :href="route('requests.index')" class="btn-ghost btn-sm"><Inbox :size="14" /> Requests</Link>
        </template>
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="surface p-4"><div class="text-xs text-ink-500">Offers</div><div class="font-display text-2xl font-extrabold">{{ stats.offers }}</div></div>
            <div class="surface p-4"><div class="text-xs text-ink-500">Active</div><div class="font-display text-2xl font-extrabold">{{ stats.active }}</div></div>
            <div class="surface p-4"><div class="flex items-center gap-1 text-xs text-ink-500"><Eye :size="12" /> Views 30d</div><div class="font-display text-2xl font-extrabold">{{ stats.views_30d }}</div></div>
            <div class="surface p-4"><div class="flex items-center gap-1 text-xs text-ink-500"><Share2 :size="12" /> Shares 30d</div><div class="font-display text-2xl font-extrabold">{{ stats.shares_30d }}</div></div>
            <div class="surface p-4"><div class="flex items-center gap-1 text-xs text-ink-500"><MessageCircle :size="12" /> Enquiries 30d</div><div class="font-display text-2xl font-extrabold">{{ stats.enquiries_30d }}</div></div>
        </div>
        <div class="mb-4 flex gap-2 md:hidden"><Link :href="route('resell.offers.index')" class="btn-ghost btn-sm flex-1">My offers</Link><Link :href="route('requests.index')" class="btn-ghost btn-sm flex-1">Requests</Link></div>
        <form class="relative mb-4" @submit.prevent="go"><Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" /><input v-model="search" class="input pl-10" placeholder="Search supplier products" /></form>
        <div v-if="products.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="p in products" :key="p.id" class="surface flex gap-3 p-3">
                <Link :href="route('products.show', p.slug)" class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-ink-100"><img v-if="p.image" :src="p.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="18" /></div></Link>
                <div class="min-w-0 flex-1 text-sm">
                    <Link :href="route('products.show', p.slug)" class="line-clamp-2 font-medium">{{ p.title }}</Link>
                    <div class="text-xs text-ink-500">{{ p.store?.name }} · {{ p.in_stock ? 'In stock' : 'Out of stock' }}</div>
                    <div class="mt-1 flex flex-wrap gap-x-3"><span>Supplier <b>{{ p.resale.supplier_price }}</b></span><span class="text-ink-500">Retail {{ p.price }}</span></div>
                    <div class="text-xs" :class="p.resale.margin.negative ? 'text-red-600' : 'text-brand-700'">Margin at retail {{ p.resale.margin.gross }}<template v-if="p.resale.margin.percent !== null"> ({{ p.resale.margin.percent }}%)</template></div>
                    <Link v-if="p.offer_id" :href="route('resell.offers.edit', p.offer_id)" class="btn-ghost btn-sm mt-2">Edit my offer</Link>
                    <Link v-else :href="route('resell.offers.create', p.id)" class="btn-dark btn-sm mt-2"><Repeat2 :size="14" /> Resell</Link>
                </div>
            </div>
        </div>
        <EmptyState v-else title="No supplier products yet" body="When suppliers open products to resellers, they appear here." color="#7c3aed"><template #icon><Repeat2 :size="28" /></template></EmptyState>
        <Pager :prev="prev_page_url" :next="next_page_url" />
    </AppShell>
</template>
