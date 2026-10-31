<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowRight, MapPin, Search, Share2, ShieldCheck, Sparkles, Store } from 'lucide-vue-next';
import SeoHead from '@/Components/SeoHead.vue';
import Wordmark from '@/Components/Wordmark.vue';
import IconCircle from '@/Components/IconCircle.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProductGrid from '@/Components/ProductGrid.vue';

const props = defineProps({ products: Array, stats: Object, seo: Object });
const q = ref('');
const go = () => router.get(route('search'), q.value ? { q: q.value } : {});

const features = [
    { icon: Store, color: '#16b26a', title: 'A storefront in a minute', body: 'Pick a handle, add your logo and you have a shareable store link like neaby/s/yourname.' },
    { icon: Sparkles, color: '#d97706', title: 'AI-written titles', body: 'Describe the item the way you would on WhatsApp — neaby suggests a clean, searchable title.' },
    { icon: MapPin, color: '#0891b2', title: 'Found nearby', body: 'Buyers discover sellers in their city first. Real stores, real people, close to you.' },
    { icon: ShieldCheck, color: '#4f46e5', title: 'Moderated & safe', body: 'Listings are reviewed and sellers verified, so buyers browse with confidence.' },
];
</script>

<template>
    <SeoHead :seo="seo" />
    <div class="relative min-h-screen overflow-hidden">
        <div class="ambient" aria-hidden="true" />

        <header class="pt-safe relative z-10">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 md:h-20 lg:px-8">
                <Wordmark size="md" />
                <nav class="flex items-center gap-1 sm:gap-2">
                    <Link :href="route('home')" class="btn hidden text-ink-600 hover:text-ink-900 sm:inline-flex">Explore</Link>
                    <Link :href="route('login')" class="btn text-ink-700 hover:text-ink-900">Sign in</Link>
                    <Link :href="route('register')" class="btn-dark">Get started</Link>
                </nav>
            </div>
        </header>

        <main class="relative z-10">
            <!-- Hero -->
            <section class="mx-auto max-w-7xl px-4 pb-10 pt-8 sm:px-6 md:pt-16 lg:px-8 lg:pt-20">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/70 px-3 py-1 text-xs font-semibold text-brand-800 backdrop-blur">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-500" /> Now open for sellers in Nigeria
                    </span>
                    <h1 class="mt-6 font-display text-[2rem] font-extrabold leading-[1.05] tracking-[-0.035em] text-ink-900 sm:text-[2.75rem] lg:text-5xl">
                        Shop the stores<br class="hidden sm:block" />
                        <span class="bg-gradient-to-r from-brand-500 via-emerald-500 to-teal-500 bg-clip-text text-transparent"> near you.</span>
                    </h1>
                    <p class="mx-auto mt-5 max-w-xl text-base text-ink-600 sm:text-lg">
                        Discover fashion, gadgets, beauty and home finds from independent sellers — or open your own store and start selling today.
                    </p>

                    <form class="mx-auto mt-8 flex max-w-xl items-center gap-2 rounded-[1.6rem] border border-ink-200 bg-white p-2 shadow-lift" role="search" @submit.prevent="go">
                        <Search :size="20" class="ml-3 shrink-0 text-ink-400" />
                        <input v-model="q" type="search" placeholder="Try “ankara dress” or “iphone 13”" class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2.5 text-[15px] placeholder:text-ink-400 focus:ring-0" />
                        <button class="btn-primary shrink-0 rounded-2xl">Search</button>
                    </form>

                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3 text-sm">
                        <Link :href="route('register')" class="btn-soft">Open your free store <ArrowRight :size="16" /></Link>
                        <Link :href="route('home')" class="btn text-ink-600 hover:text-ink-900">Browse without an account</Link>
                    </div>
                </div>

                <!-- Category orbit -->
                <div class="no-scrollbar -mx-4 mt-12 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:justify-center sm:px-0">
                    <Link v-for="c in $page.props.categories.slice(0, 10)" :key="c.id" :href="route('categories.show', c.slug)" class="chip bg-white/80 backdrop-blur">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full" :style="{ background: c.accent + '1f', color: c.accent }"><AppIcon :name="c.icon" :size="13" /></span>
                        {{ c.name }}
                    </Link>
                </div>
            </section>

            <!-- Fresh listings -->
            <section v-if="products.length" class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="mb-5 flex items-end justify-between">
                    <div>
                        <h2 class="font-display text-xl font-extrabold tracking-tight text-ink-900 md:text-[1.375rem]">Fresh on neaby</h2>
                        <p class="mt-1 text-sm text-ink-500">{{ stats.products }} items from {{ stats.stores }} stores and counting.</p>
                    </div>
                    <Link :href="route('home')" class="btn-ghost hidden sm:inline-flex">See all <ArrowRight :size="16" /></Link>
                </div>
                <ProductGrid :products="products" />
            </section>

            <!-- Features -->
            <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="f in features" :key="f.title" class="surface p-6">
                        <IconCircle :color="f.color" :size="48"><component :is="f.icon" :size="22" /></IconCircle>
                        <h3 class="mt-4 font-display text-lg font-bold">{{ f.title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink-500">{{ f.body }}</p>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-[2rem] bg-ink-950 px-6 py-12 text-center text-white sm:px-12 md:py-16">
                    <div class="absolute -right-24 -top-24 h-80 w-80 animate-drift rounded-full bg-brand-500/40 blur-3xl" />
                    <div class="absolute -bottom-24 -left-16 h-72 w-72 animate-drift-alt rounded-full bg-teal-400/25 blur-3xl" />
                    <div class="relative">
                        <IconCircle color="#38cc83" :size="56" class="mx-auto"><Share2 :size="24" /></IconCircle>
                        <h2 class="mt-5 font-display text-xl font-extrabold tracking-tight md:text-2xl">Your store. One link. Everywhere.</h2>
                        <p class="mx-auto mt-3 max-w-lg text-white/70">Share your neaby store on WhatsApp, Instagram and TikTok. Buyers see a fast, beautiful page — no app needed.</p>
                        <Link :href="route('register')" class="btn-primary btn-lg mt-8">Start selling free <ArrowRight :size="18" /></Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t border-ink-200/70 bg-white/60 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-sm text-ink-500 sm:flex-row sm:px-6 lg:px-8">
                <Wordmark size="sm" />
                <div class="flex gap-5">
                    <Link :href="route('categories.index')" class="hover:text-ink-900">Categories</Link>
                    <Link :href="route('search')" class="hover:text-ink-900">Search</Link>
                    <Link :href="route('register')" class="hover:text-ink-900">Sell</Link>
                </div>
                <span>© {{ new Date().getFullYear() }} <span class="font-brand font-bold lowercase">neaby</span></span>
            </div>
        </footer>
    </div>
</template>
