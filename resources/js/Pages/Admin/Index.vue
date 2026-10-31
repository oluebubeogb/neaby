<script setup>
import { Link } from '@inertiajs/vue3';
import { Boxes, CircleAlert, Clock, Shapes, Store, Users } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import IconCircle from '@/Components/IconCircle.vue';

defineProps({ stats: Object, recent: Array, reviewRequired: Boolean, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Admin" wide>
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <Link :href="route('admin.products.index')" class="btn-primary"><Clock :size="17" /> Review queue <span v-if="stats.pending" class="rounded-full bg-white/25 px-2 text-xs">{{ stats.pending }}</span></Link>
            <Link :href="route('admin.products.index', { status: 'all' })" class="btn-ghost"><Boxes :size="17" /> All listings</Link>
            <Link :href="route('admin.categories.index')" class="btn-ghost"><Shapes :size="17" /> Categories</Link>
            <Link :href="route('admin.plans.index')" class="btn-ghost">Plans</Link>
            <Link :href="route('admin.analytics')" class="btn-ghost">Analytics</Link>
            <Link :href="route('admin.refunds.index')" class="btn-ghost">Refunds</Link>
            <Link :href="route('admin.sponsored.index')" class="btn-ghost">Sponsored</Link>
            <span class="badge ml-auto" :class="reviewRequired ? 'bg-amber-100 text-amber-800' : 'bg-brand-100 text-brand-800'">Review {{ reviewRequired ? 'required' : 'off' }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div v-for="s in [
                { k: 'users', l: 'Users', i: Users, c: '#0891b2' },
                { k: 'stores', l: 'Stores', i: Store, c: '#16b26a' },
                { k: 'products', l: 'Listings', i: Boxes, c: '#4f46e5' },
                { k: 'published', l: 'Live', i: Boxes, c: '#16b26a' },
                { k: 'pending', l: 'Pending', i: Clock, c: '#d97706' },
                { k: 'rejected', l: 'Rejected', i: CircleAlert, c: '#e11d48' },
            ]" :key="s.k" class="surface p-4">
                <IconCircle :color="s.c" :size="40"><component :is="s.i" :size="18" /></IconCircle>
                <div class="mt-3 font-display text-xl font-extrabold">{{ stats[s.k] }}</div>
                <div class="text-sm text-ink-500">{{ s.l }}</div>
            </div>
        </div>

        <h2 class="section-title mb-3 mt-8">Recent activity</h2>
        <div class="surface divide-y divide-ink-100">
            <div v-for="r in recent" :key="r.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-sm">
                <span class="badge bg-ink-100 font-mono text-ink-700">{{ r.action }}</span>
                <span class="text-ink-700">{{ r.subject }}</span>
                <span v-if="r.reason" class="text-ink-500">— {{ r.reason }}</span>
                <span class="ml-auto text-ink-400">{{ r.actor ? '@' + r.actor : 'system' }} · {{ r.at }}</span>
            </div>
            <p v-if="!recent.length" class="p-6 text-center text-sm text-ink-500">No activity yet.</p>
        </div>
    </AppShell>
</template>
