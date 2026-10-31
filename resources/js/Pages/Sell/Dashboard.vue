<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Archive, ExternalLink, Eye, ImageOff, MoreHorizontal, Pause, Pencil, Play, Plus, Settings2, Store, Trash2, TrendingUp, Zap } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import IconCircle from '@/Components/IconCircle.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';
import Dropdown from '@/Components/Dropdown.vue';

const props = defineProps({ store: Object, products: Object, counts: Object, stats: Object, statusFilter: String, reviewRequired: Boolean, seo: Object });

const tabs = computed(() => [
    { key: null, label: 'All', count: Object.values(props.counts).reduce((a, b) => a + b, 0) },
    { key: 'published', label: 'Live', count: props.counts.published },
    { key: 'pending', label: 'In review', count: props.counts.pending },
    { key: 'draft', label: 'Drafts', count: props.counts.draft },
    { key: 'paused', label: 'Paused', count: props.counts.paused },
    { key: 'rejected', label: 'Needs changes', count: props.counts.rejected },
    { key: 'archived', label: 'Archived', count: props.counts.archived },
].filter((t) => t.key === null || t.count > 0 || t.key === props.statusFilter));

const tone = {
    published: 'bg-brand-100 text-brand-800', pending: 'bg-amber-100 text-amber-800', draft: 'bg-ink-100 text-ink-700',
    paused: 'bg-sky-100 text-sky-800', rejected: 'bg-red-100 text-red-700', archived: 'bg-ink-100 text-ink-500',
};
const label = { published: 'Live', pending: 'In review', draft: 'Draft', paused: 'Paused', rejected: 'Needs changes', archived: 'Archived' };

const act = (p, action) => router.post(route('sell.products.status', p.id), { action }, { preserveScroll: true });
const remove = (p) => confirm(`Delete “${p.title}”? This can't be undone.`) && router.delete(route('sell.products.destroy', p.id), { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Seller hub" wide>
        <template #actions>
            <Link :href="route('sell.products.create')" class="btn-primary"><Plus :size="18" /> New listing</Link>
        </template>

        <!-- Store header -->
        <section class="surface relative mb-6 overflow-hidden p-5 md:p-6">
            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full blur-3xl" :style="{ background: store.accent + '30' }" />
            <div class="relative flex flex-wrap items-center gap-4">
                <Avatar :src="store.logo" :initials="store.initials" :accent="store.accent" :size="60" rounded="rounded-2xl" />
                <div class="min-w-0 flex-1">
                    <h1 class="font-display text-xl font-extrabold tracking-tight md:text-2xl">{{ store.name }}</h1>
                    <a :href="route('stores.show', store.slug)" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-brand-700">{{ route('stores.show', store.slug).replace(/^https?:\/\//, '') }} <ExternalLink :size="13" /></a>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('stores.show', store.slug)" class="btn-ghost btn-sm"><Store :size="15" /> View</Link>
                    <Link :href="route('sell.store.edit')" class="btn-ghost btn-sm"><Settings2 :size="15" /> Settings</Link>
                </div>
            </div>
            <div class="relative mt-4 flex flex-wrap gap-2">
                <Link :href="route('sell.orders.index')" class="chip">Orders</Link>
                <Link :href="route('sell.insights')" class="chip">Insights</Link>
                <Link :href="route('sell.import.index')" class="chip">Import CSV/XLSX</Link>
                <Link :href="route('sell.bulk.index')" class="chip">Bulk edit</Link>
                <Link :href="route('sell.resale.index')" class="chip">Supplier resale</Link>
            </div>
            <div class="relative mt-5 grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-ink-50 p-3 md:p-4">
                    <div class="flex items-center gap-2 text-xs font-semibold text-ink-500"><Zap :size="14" class="text-brand-600" /> Live</div>
                    <div class="mt-1 font-display text-2xl font-extrabold">{{ stats.live }}</div>
                </div>
                <div class="rounded-2xl bg-ink-50 p-3 md:p-4">
                    <div class="flex items-center gap-2 text-xs font-semibold text-ink-500"><Eye :size="14" class="text-sky-600" /> Item views</div>
                    <div class="mt-1 font-display text-2xl font-extrabold">{{ stats.views }}</div>
                </div>
                <div class="rounded-2xl bg-ink-50 p-3 md:p-4">
                    <div class="flex items-center gap-2 text-xs font-semibold text-ink-500"><TrendingUp :size="14" class="text-violet-600" /> Store visits</div>
                    <div class="mt-1 font-display text-2xl font-extrabold">{{ stats.store_views }}</div>
                </div>
            </div>
        </section>

        <p v-if="reviewRequired" class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">New listings are reviewed by the neaby team before they go live — usually within a few hours.</p>

        <!-- Tabs -->
        <div class="no-scrollbar -mx-3 mb-4 flex gap-2 overflow-x-auto px-3 sm:mx-0 sm:px-0">
            <Link v-for="t in tabs" :key="t.key || 'all'" :href="route('sell.dashboard', t.key ? { status: t.key } : {})" class="chip" :class="(statusFilter || null) === t.key && 'chip-active'" preserve-scroll>
                {{ t.label }} <span class="opacity-60">{{ t.count }}</span>
            </Link>
        </div>

        <!-- Listings -->
        <div v-if="products.data.length" class="surface divide-y divide-ink-100 overflow-hidden">
            <div v-for="p in products.data" :key="p.id" class="flex items-center gap-3 p-3 md:gap-4 md:p-4">
                <Link :href="route('sell.products.edit', p.id)" class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-ink-100 md:h-20 md:w-20">
                    <img v-if="p.image" :src="p.image" alt="" class="h-full w-full object-cover" loading="lazy" />
                    <div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="20" /></div>
                </Link>
                <div class="min-w-0 flex-1">
                    <Link :href="route('sell.products.edit', p.id)" class="line-clamp-1 font-semibold text-ink-900 hover:text-brand-700">{{ p.title }}</Link>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-500">
                        <span class="font-semibold text-ink-800">{{ p.price }}</span>
                        <span class="badge" :class="tone[p.status]">{{ label[p.status] }}</span>
                        <span class="hidden sm:inline">{{ p.views }} views</span>
                        <span class="hidden md:inline">Updated {{ p.updated_at }}</span>
                    </div>
                    <p v-if="p.status === 'rejected' && p.moderation_note" class="mt-1 text-xs text-red-700">{{ p.moderation_note }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Link :href="route('sell.products.edit', p.id)" class="icon-btn hidden sm:inline-flex" aria-label="Edit"><Pencil :size="17" /></Link>
                    <Dropdown align="right" width="48">
                        <template #trigger><button class="icon-btn" aria-label="More actions"><MoreHorizontal :size="18" /></button></template>
                        <template #content>
                            <div class="p-1.5 text-sm">
                                <Link :href="route('products.show', p.slug)" class="menu-item"><Eye :size="16" /> Preview</Link>
                                <Link :href="route('sell.products.edit', p.id)" class="menu-item"><Pencil :size="16" /> Edit</Link>
                                <button v-if="p.status === 'published'" class="menu-item" @click="act(p, 'pause')"><Pause :size="16" /> Pause</button>
                                <button v-if="['paused', 'draft', 'rejected', 'archived'].includes(p.status)" class="menu-item" @click="act(p, 'resume')"><Play :size="16" /> {{ p.status === 'paused' ? 'Resume' : 'Publish' }}</button>
                                <button v-if="p.status !== 'archived'" class="menu-item" @click="act(p, 'archive')"><Archive :size="16" /> Archive</button>
                                <button class="menu-item text-red-600 hover:bg-red-50" @click="remove(p)"><Trash2 :size="16" /> Delete</button>
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </div>
        <EmptyState v-else :title="statusFilter ? 'Nothing in this tab' : 'Add your first listing'" body="Photos + a short description is all you need. We'll suggest a title.">
            <template #icon><Plus :size="28" /></template>
            <Link :href="route('sell.products.create')" class="btn-primary">New listing</Link>
        </EmptyState>
        <Pager :prev="products.prev_page_url" :next="products.next_page_url" />

        <Link :href="route('sell.products.create')" class="btn-primary fixed bottom-[calc(5.5rem+var(--safe-bottom))] right-4 z-30 h-14 w-14 rounded-full p-0 shadow-glow md:hidden" aria-label="New listing"><Plus :size="24" /></Link>
    </AppShell>
</template>
