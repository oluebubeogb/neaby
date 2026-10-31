<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Camera, Share2, Store } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import IconCircle from '@/Components/IconCircle.vue';

defineProps({ seo: Object });
const steps = [
    { icon: Store, color: '#16b26a', title: 'Open your store', body: 'Name, handle, logo. Your link is ready instantly.' },
    { icon: Camera, color: '#d97706', title: 'Snap & list', body: 'Add photos and a quick description — AI writes the title.' },
    { icon: Share2, color: '#4f46e5', title: 'Share everywhere', body: 'Post your store link to WhatsApp, Instagram and TikTok.' },
];
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Sell">
        <section class="relative mx-auto max-w-3xl overflow-hidden rounded-[2rem] bg-ink-950 px-6 py-10 text-white md:px-12 md:py-14">
            <div class="absolute -right-20 -top-24 h-72 w-72 animate-drift rounded-full bg-brand-500/40 blur-3xl" />
            <div class="absolute -bottom-24 -left-10 h-64 w-64 animate-drift-alt rounded-full bg-teal-400/25 blur-3xl" />
            <div class="relative">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-300">Sell on neaby</p>
                <h1 class="mt-3 font-display text-xl font-extrabold tracking-tight md:text-2xl">Turn your WhatsApp catalogue into a real store.</h1>
                <p class="mt-3 max-w-lg text-white/70">Free to start. No website to build. Buyers nearby find you through search and their For You feed.</p>
                <Link :href="route('sell.store.create')" class="btn-primary btn-lg mt-8">Open my store <ArrowRight :size="18" /></Link>
            </div>
        </section>
        <div class="mx-auto mt-6 grid max-w-3xl gap-3 md:grid-cols-3">
            <div v-for="(s, i) in steps" :key="s.title" class="surface p-5">
                <div class="flex items-center gap-3">
                    <IconCircle :color="s.color" :size="44"><component :is="s.icon" :size="20" /></IconCircle>
                    <span class="text-xs font-bold text-ink-400">STEP {{ i + 1 }}</span>
                </div>
                <h3 class="mt-3 font-display font-bold">{{ s.title }}</h3>
                <p class="mt-1 text-sm text-ink-500">{{ s.body }}</p>
            </div>
        </div>
    </AppShell>
</template>
