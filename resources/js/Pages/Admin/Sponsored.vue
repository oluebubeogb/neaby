<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

defineProps({ placements: Array, seo: Object });
const set = (p, status) => confirm(`Mark placement ${status}?`) && router.post(route('admin.sponsored.update', p.id), { status }, { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Sponsored" :back="route('admin.index')" wide>
        <div class="surface divide-y divide-ink-100 text-sm">
            <div v-for="p in placements" :key="p.id" class="flex flex-wrap items-center gap-3 p-4">
                <div class="flex-1"><Link v-if="p.slug" :href="route('products.show', p.slug)" class="font-medium hover:underline">{{ p.title }}</Link><div class="text-xs text-ink-500">{{ p.days }}d · {{ p.price }}<template v-if="p.starts"> · {{ p.starts }}–{{ p.ends }}</template> · {{ p.impressions }} impressions · {{ p.clicks }} clicks</div></div>
                <span class="badge capitalize bg-ink-100">{{ p.status }}</span>
                <template v-if="['active', 'pending_payment'].includes(p.status)"><button class="btn-ghost btn-sm" @click="set(p, 'ended')">End</button><button class="btn-ghost btn-sm text-red-600" @click="set(p, 'rejected')">Reject</button></template>
            </div>
            <p v-if="!placements.length" class="p-5 text-ink-500">No placements yet.</p>
        </div>
    </AppShell>
</template>
