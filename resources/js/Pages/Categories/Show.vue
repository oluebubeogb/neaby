<script setup>
import { Link } from '@inertiajs/vue3';
import { PackageOpen } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppIcon from '@/Components/AppIcon.vue';
import IconCircle from '@/Components/IconCircle.vue';
import ProductGrid from '@/Components/ProductGrid.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';

defineProps({ category: Object, products: Object, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="category.name" :back="route('categories.index')" wide>
        <div class="surface relative mb-6 flex items-center gap-4 overflow-hidden p-5 md:p-7">
            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full blur-3xl" :style="{ background: category.accent + '33' }" />
            <IconCircle :color="category.accent" :size="60"><AppIcon :name="category.icon" :size="28" /></IconCircle>
            <div class="relative">
                <h1 class="font-display text-xl font-extrabold tracking-tight md:text-[1.375rem]">{{ category.name }}</h1>
                <p class="text-sm text-ink-500">{{ products.total }} items from local stores</p>
            </div>
            <Link :href="route('search', { category: category.slug })" class="btn-ghost relative ml-auto hidden sm:inline-flex">Filter & sort</Link>
        </div>

        <ProductGrid v-if="products.data.length" :products="products.data" />
        <EmptyState v-else title="No listings yet" :body="`Nobody has listed in ${category.name} yet — yours could be first.`" :color="category.accent">
            <template #icon><PackageOpen :size="28" /></template>
            <Link :href="route('sell.products.create')" class="btn-primary">List an item</Link>
        </EmptyState>
        <Pager :prev="products.prev_page_url" :next="products.next_page_url" />
    </AppShell>
</template>
