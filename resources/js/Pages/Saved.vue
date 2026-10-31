<script setup>
import { Link } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import ProductCard from '@/Components/ProductCard.vue';
import SaveButton from '@/Components/SaveButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';

defineProps({ products: Array, next_page_url: String, prev_page_url: String, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Saved" wide>
        <div v-if="products.length" class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-5">
            <div v-for="p in products" :key="p.id" class="relative" :class="p.available_now ? '' : 'opacity-60'">
                <ProductCard :product="p" />
                <div class="absolute right-2 top-2 rounded-full bg-white/90 shadow"><SaveButton :product-id="p.id" :saved="true" :size="17" /></div>
                <span v-if="!p.available_now" class="badge absolute bottom-24 left-2 bg-ink-900 text-white">No longer listed</span>
            </div>
        </div>
        <EmptyState v-else title="Nothing saved yet" body="Tap the heart on any listing to keep it here." color="#ef4444">
            <template #icon><Heart :size="28" /></template>
            <Link :href="route('home')" class="btn-primary">Discover listings</Link>
        </EmptyState>
        <Pager :prev="prev_page_url" :next="next_page_url" />
    </AppShell>
</template>
