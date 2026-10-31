<script setup>
import { Link } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppIcon from '@/Components/AppIcon.vue';
import IconCircle from '@/Components/IconCircle.vue';

defineProps({ list: Array, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Categories">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <Link
                v-for="c in list"
                :key="c.id"
                :href="route('categories.show', c.slug)"
                class="surface group relative overflow-hidden p-4 transition hover:-translate-y-0.5 hover:shadow-lift md:p-5"
            >
                <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full opacity-60 blur-2xl transition group-hover:opacity-100" :style="{ background: c.accent + '33' }" />
                <IconCircle :color="c.accent" :size="52"><AppIcon :name="c.icon" :size="24" /></IconCircle>
                <div class="relative mt-4 font-display font-bold leading-tight text-ink-900">{{ c.name }}</div>
                <div class="relative mt-0.5 text-xs text-ink-500">{{ c.count }} {{ c.count === 1 ? 'item' : 'items' }}</div>
            </Link>
        </div>
    </AppShell>
</template>
