<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ImageOff, Package } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';
import OrderStatus from '@/Components/OrderStatus.vue';

const props = defineProps({ orders: Array, counts: Object, statusFilter: String, next_page_url: String, prev_page_url: String, seo: Object });
const labels = { paid: 'To fulfil', processing: 'Processing', shipped: 'Shipped', ready_for_pickup: 'Ready for pickup', delivered: 'Delivered', completed: 'Completed', pending_payment: 'Awaiting payment', cancelled: 'Cancelled', refunded: 'Refunded', payment_failed: 'Failed' };
const tabs = computed(() => [{ key: null, label: 'All' }, ...Object.keys(labels).filter((k) => (props.counts?.[k] || 0) > 0 || k === props.statusFilter).map((k) => ({ key: k, label: labels[k], count: props.counts[k] }))]);
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Orders" :back="route('sell.dashboard')" wide>
        <div class="no-scrollbar -mx-3 mb-4 flex gap-2 overflow-x-auto px-3">
            <Link v-for="t in tabs" :key="t.key || 'all'" :href="route('sell.orders.index', t.key ? { status: t.key } : {})" class="chip shrink-0" :class="statusFilter === t.key ? 'chip-active' : ''">{{ t.label }}<span v-if="t.count" class="ml-1 opacity-70">{{ t.count }}</span></Link>
        </div>
        <div v-if="orders.length" class="surface divide-y divide-ink-100 overflow-hidden">
            <Link v-for="o in orders" :key="o.id" :href="route('sell.orders.show', o.id)" class="flex items-center gap-3 p-4 transition hover:bg-ink-50">
                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-ink-100"><img v-if="o.image" :src="o.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="16" /></div></div>
                <div class="min-w-0 flex-1"><div class="truncate font-medium">{{ o.first_item }}<span v-if="o.items_count > 1" class="text-ink-500"> ×{{ o.items_count }}</span></div><div class="text-xs text-ink-500">#{{ o.number }} · {{ o.buyer }} · {{ o.delivery_method }} · {{ o.created_ago }}</div></div>
                <div class="text-right"><div class="font-display font-bold">{{ o.total }}</div><OrderStatus :status="o.status" :label="o.status_label" /></div>
            </Link>
        </div>
        <EmptyState v-else title="No orders here" body="Turn on neaby checkout on your listings so buyers can pay you online.">
            <template #icon><Package :size="28" /></template>
            <Link :href="route('sell.bulk.index')" class="btn-primary">Enable checkout in bulk</Link>
        </EmptyState>
        <Pager :prev="prev_page_url" :next="next_page_url" />
    </AppShell>
</template>
