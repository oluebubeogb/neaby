<script setup>
import { Link } from '@inertiajs/vue3';
import { ImageOff, Inbox } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';

defineProps({ tab: String, requests: Array, counts: Object, next_page_url: String, prev_page_url: String, seo: Object });
const tone = { pending: 'bg-amber-100 text-amber-800', availability_confirmed: 'bg-sky-100 text-sky-800', terms_proposed: 'bg-violet-100 text-violet-800', accepted: 'bg-brand-100 text-brand-800', fulfilled: 'bg-brand-100 text-brand-800', declined: 'bg-red-100 text-red-700', cancelled: 'bg-ink-100 text-ink-600', expired: 'bg-ink-100 text-ink-600' };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Supplier requests" :back="route('resell.index')">
        <div class="mx-auto max-w-3xl">
            <div class="mb-4 flex gap-2">
                <Link :href="route('requests.index', { tab: 'outgoing' })" class="chip" :class="tab === 'outgoing' ? 'chip-active' : ''">Sent <span v-if="counts.outgoing" class="ml-1 opacity-70">{{ counts.outgoing }}</span></Link>
                <Link :href="route('requests.index', { tab: 'incoming' })" class="chip" :class="tab === 'incoming' ? 'chip-active' : ''">Received <span v-if="counts.incoming" class="ml-1 opacity-70">{{ counts.incoming }}</span></Link>
            </div>
            <div v-if="requests.length" class="surface divide-y divide-ink-100 overflow-hidden">
                <Link v-for="r in requests" :key="r.id" :href="route('requests.show', r.id)" class="flex items-center gap-3 p-4 text-sm hover:bg-ink-50">
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-ink-100"><img v-if="r.product?.image" :src="r.product.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="14" /></div></div>
                    <div class="min-w-0 flex-1"><div class="truncate font-medium">{{ r.product?.title }}</div><div class="text-xs text-ink-500">{{ r.role === 'supplier' ? 'From' : 'To' }} @{{ r.counterparty.username }} · qty {{ r.requested_quantity }} · {{ r.created_ago }}</div></div>
                    <span class="badge" :class="tone[r.status]">{{ r.status_label }}</span>
                </Link>
            </div>
            <EmptyState v-else title="No requests" body="Stock checks and holds between resellers and suppliers appear here." color="#7c3aed"><template #icon><Inbox :size="28" /></template></EmptyState>
            <Pager :prev="prev_page_url" :next="next_page_url" />
        </div>
    </AppShell>
</template>
