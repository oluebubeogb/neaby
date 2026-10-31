<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Compass, Loader2, Maximize2, PlusCircle, Sparkles } from 'lucide-vue-next';
import ImmersiveViewer from '@/Components/ImmersiveViewer.vue';
import Tip from '@/Components/Tip.vue';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProductGrid from '@/Components/ProductGrid.vue';
import StoreChip from '@/Components/StoreChip.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    feed: Array,
    page: Number,
    hasMore: Boolean,
    personalised: Boolean,
    activeCategory: String,
    spotlightStores: Array,
    seo: Object,
});

const loading = ref(false);
const sentinel = ref(null);
let observer;

function loadMore() {
    if (loading.value || !props.hasMore) return;
    loading.value = true;
    router.reload({
        data: { page: props.page + 1, category: props.activeCategory || undefined },
        only: ['feed', 'page', 'hasMore'],
        preserveUrl: true,
        onFinish: () => (loading.value = false),
    });
}

function pickCategory(slug) {
    router.get(route('home'), slug ? { category: slug } : {}, { preserveScroll: false, reset: ['feed'] });
}

onMounted(() => {
    observer = new IntersectionObserver((entries) => entries[0]?.isIntersecting && loadMore(), { rootMargin: '600px' });
    if (sentinel.value) observer.observe(sentinel.value);
});
onBeforeUnmount(() => observer?.disconnect());

const immersive = ref(false);

const greeting = (() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
})();
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell wide>
        <!-- Greeting + category rail -->
        <section class="mb-5 md:mb-7">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-ink-500">{{ greeting }}{{ $page.props.auth.user ? ', ' + $page.props.auth.user.name.split(' ')[0] : '' }}</p>
                    <h1 class="mt-0.5 flex items-center font-display text-xl font-extrabold tracking-tight text-ink-900 md:text-[1.375rem]">
                        For you
                        <span v-if="personalised" class="ml-2 inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 align-middle text-xs font-semibold text-brand-700"><Sparkles :size="12" /> tuned to you</span>
                        <Tip v-if="feed.length" label="Immersive view" side="right">
                            <button class="ml-2 flex h-8 w-8 items-center justify-center rounded-full bg-ink-900 text-white shadow-lift transition hover:bg-ink-800" aria-label="Open For you in immersive view" @click="immersive = true"><Maximize2 :size="15" /></button>
                        </Tip>
                    </h1>
                </div>
                <Link :href="route('sell.products.create')" class="btn-primary hidden md:inline-flex"><PlusCircle :size="18" /> List an item</Link>
            </div>

            <div class="no-scrollbar -mx-3 mt-4 flex gap-2 overflow-x-auto px-3 pb-1 sm:-mx-4 sm:px-4 md:mx-0 md:px-0">
                <button class="chip" :class="!activeCategory && 'chip-active'" @click="pickCategory(null)"><Compass :size="15" /> All</button>
                <button
                    v-for="c in $page.props.categories"
                    :key="c.id"
                    class="chip"
                    :class="activeCategory === c.slug && 'chip-active'"
                    @click="pickCategory(c.slug)"
                >
                    <AppIcon :name="c.icon" :size="15" :style="activeCategory === c.slug ? {} : { color: c.accent }" />
                    {{ c.name }}
                </button>
            </div>
        </section>

        <!-- Spotlight stores -->
        <section v-if="spotlightStores?.length && !activeCategory" class="mb-7">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="section-title">Stores to know</h2>
            </div>
            <div class="no-scrollbar -mx-3 flex gap-3 overflow-x-auto px-3 pb-2 sm:-mx-4 sm:px-4 md:mx-0 md:px-0">
                <StoreChip v-for="s in spotlightStores" :key="s.id" :store="s" />
            </div>
        </section>

        <ProductGrid v-if="feed.length" :products="feed" />
        <EmptyState v-else title="Nothing here yet" body="Be the first to list something in this corner of neaby.">
            <template #icon><Compass :size="28" /></template>
            <Link :href="route('sell.products.create')" class="btn-primary">List an item</Link>
        </EmptyState>

        <ImmersiveViewer :show="immersive" :items="feed" title="For you" @close="immersive = false" @end="loadMore" />

        <div ref="sentinel" class="flex h-20 items-center justify-center">
            <Loader2 v-if="loading" :size="22" class="animate-spin text-brand-500" />
            <button v-else-if="hasMore" class="btn-ghost" @click="loadMore">Load more</button>
            <span v-else-if="feed.length > 12" class="text-sm text-ink-400">You're all caught up ✨</span>
        </div>
    </AppShell>
</template>
