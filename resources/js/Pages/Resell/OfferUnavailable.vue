<script setup>
import { Link } from '@inertiajs/vue3';
import { PackageX } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Offer unavailable">
        <div class="mx-auto max-w-xl">
            <EmptyState title="This offer isn't available right now" body="It may have sold out or been paused by the reseller." color="#64748b">
                <template #icon><PackageX :size="28" /></template>
                <Link :href="route('home')" class="btn-primary">Browse neaby</Link>
            </EmptyState>
        </div>
    </AppShell>
</template>
