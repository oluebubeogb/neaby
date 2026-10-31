<script setup>
import { Link } from '@inertiajs/vue3';
import { ImageOff, Package } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';
import OrderStatus from '@/Components/OrderStatus.vue';

defineProps({ orders: Array, next_page_url: String, prev_page_url: String, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Your orders">
        <div class="mx-auto max-w-3xl">
            <div v-if="orders.length" class="surface divide-y divide-ink-100 overflow-hidden">
                <Link v-for="o in orders" :key="o.id" :href="route('orders.show', o.id)" class="flex items-center gap-3 p-4 transition hover:bg-ink-50">
                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-ink-100"><img v-if="o.image" :src="o.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="18" /></div></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ o.first_item }}<span v-if="o.items_count > 1" class="text-ink-500"> + {{ o.items_count - 1 }} more</span></div>
                        <div class="text-xs text-ink-500">#{{ o.number }} · {{ o.store?.name }} · {{ o.created_ago }}</div>
                    </div>
                    <div class="text-right"><div class="font-display font-bold">{{ o.total }}</div><OrderStatus :status="o.status" :label="o.status_label" /></div>
                </Link>
            </div>
            <EmptyState v-else title="No orders yet" body="When you buy with neaby checkout, your orders and their delivery progress show up here.">
                <template #icon><Package :size="28" /></template>
                <Link :href="route('home')" class="btn-primary">Start shopping</Link>
            </EmptyState>
            <Pager :prev="prev_page_url" :next="next_page_url" />
        </div>
    </AppShell>
</template>
