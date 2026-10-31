<script setup>
import { Link } from '@inertiajs/vue3';
import { Check, Repeat2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import IconCircle from '@/Components/IconCircle.vue';

defineProps({ seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Resell">
        <div class="surface relative mx-auto max-w-xl overflow-hidden px-6 py-12 text-center">
            <div class="absolute -right-10 -top-16 h-48 w-48 rounded-full bg-violet-200/50 blur-3xl" />
            <div class="relative">
                <IconCircle color="#7c3aed" :size="72" class="mx-auto"><Repeat2 :size="32" /></IconCircle>
                <h1 class="mt-5 font-display text-xl font-extrabold tracking-tight">Sell without holding stock</h1>
                <p class="mx-auto mt-2 max-w-sm text-ink-500">Reseller members browse supplier products, see the supplier price, and publish their own offer at their own price.</p>
                <ul class="mx-auto mt-5 grid max-w-xs gap-2 text-left text-sm">
                    <li class="flex gap-2"><Check :size="16" class="text-brand-600" /> Supplier prices &amp; terms</li>
                    <li class="flex gap-2"><Check :size="16" class="text-brand-600" /> Your own shareable offer pages</li>
                    <li class="flex gap-2"><Check :size="16" class="text-brand-600" /> Stock checks &amp; supplier requests</li>
                    <li class="flex gap-2"><Check :size="16" class="text-brand-600" /> Offer analytics</li>
                </ul>
                <Link :href="route('membership.index')" class="btn-primary btn-lg mt-7">Become a reseller</Link>
            </div>
        </div>
    </AppShell>
</template>
