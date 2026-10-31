<script setup>
import { router } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

defineProps({ refunds: Array, seo: Object });
const set = (r, status) => confirm(`Mark refund ${status}?`) && router.post(route('admin.refunds.update', r.id), { status }, { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Refunds" :back="route('admin.index')" wide>
        <p class="hint mb-3">With the fake provider refunds are bookkeeping only. With Monnify, process the refund in the Monnify dashboard, then mark it processed here.</p>
        <div class="surface divide-y divide-ink-100 text-sm">
            <div v-for="r in refunds" :key="r.id" class="flex flex-wrap items-center gap-3 p-4">
                <div class="flex-1"><div class="font-medium">Order {{ r.order }} · {{ r.amount }}</div><div class="text-xs text-ink-500">{{ r.at }}<template v-if="r.reason"> · {{ r.reason }}</template></div></div>
                <span class="badge capitalize" :class="r.status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-ink-100'">{{ r.status }}</span>
                <template v-if="r.status === 'pending'"><button class="btn-primary btn-sm" @click="set(r, 'processed')">Processed</button><button class="btn-ghost btn-sm text-red-600" @click="set(r, 'rejected')">Reject</button></template>
            </div>
            <p v-if="!refunds.length" class="p-5 text-ink-500">No refunds.</p>
        </div>
    </AppShell>
</template>
