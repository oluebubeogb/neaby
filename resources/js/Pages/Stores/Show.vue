<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Instagram, MapPin, MessageCircle, PackageOpen, Pencil, Plus, Share2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import ProductGrid from '@/Components/ProductGrid.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';
import ShareSheet from '@/Components/ShareSheet.vue';

const props = defineProps({ store: Object, isOwner: Boolean, products: Object, storeCategories: Array, activeCategory: String, shareUrl: String, seo: Object });

const url = computed(() => route('stores.show', props.store.slug));
const wa = computed(() => {
    if (!props.store.whatsapp) return null;
    let n = props.store.whatsapp.replace(/[^0-9]/g, '');
    if (n.startsWith('0')) n = '234' + n.slice(1);
    return `https://wa.me/${n}?text=${encodeURIComponent(`Hi ${props.store.name}! I found your store on neaby: ${url.value}`)}`;
});
const copied = ref(false);
const sharing = ref(false);
function share() { sharing.value = true; }
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="store.name" wide>
        <!-- Hero -->
        <section class="-mx-3 -mt-4 sm:mx-0 sm:mt-0">
            <div class="relative h-40 overflow-hidden sm:h-52 sm:rounded-[2rem] md:h-64" :style="{ background: `linear-gradient(135deg, ${store.accent}, ${store.accent}99 55%, #0a4b31)` }">
                <img v-if="store.cover" :src="store.cover" alt="" class="h-full w-full object-cover" />
                <div v-else class="absolute -right-10 -top-16 h-64 w-64 animate-drift rounded-full bg-white/20 blur-3xl" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent" />
            </div>
            <div class="relative px-4 sm:px-6">
                <div class="-mt-12 flex flex-col gap-4 sm:-mt-14 md:flex-row md:items-end">
                    <Avatar :src="store.logo" :initials="store.initials" :accent="store.accent" :size="104" rounded="rounded-[1.75rem]" class="shadow-lift ring-4" />
                    <div class="min-w-0 flex-1 md:pb-1">
                        <h1 class="font-display text-2xl font-extrabold tracking-tight text-ink-900 md:text-3xl">{{ store.name }}</h1>
                        <p v-if="store.tagline" class="mt-0.5 text-ink-600">{{ store.tagline }}</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-500">
                            <span v-if="store.city || store.state" class="inline-flex items-center gap-1"><MapPin :size="14" /> {{ [store.city, store.state].filter(Boolean).join(', ') }}</span>
                            <span>{{ store.products_count }} items</span>
                            <span>Joined {{ store.joined }}</span>
                            <Link v-if="store.owner" :href="route('users.show', store.owner.username)" class="hover:text-ink-800">@{{ store.owner.username }}</Link>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 md:pb-1">
                        <template v-if="isOwner">
                            <Link :href="route('sell.products.create')" class="btn-primary"><Plus :size="17" /> Add item</Link>
                            <Link :href="route('sell.store.edit')" class="btn-ghost"><Pencil :size="16" /> Edit store</Link>
                        </template>
                        <a v-else-if="wa" :href="wa" target="_blank" rel="noopener" class="btn-primary"><MessageCircle :size="17" /> WhatsApp</a>
                        <a v-if="store.instagram" :href="`https://instagram.com/${store.instagram.replace('@', '')}`" target="_blank" rel="noopener" class="btn-ghost" aria-label="Instagram"><Instagram :size="17" /></a>
                        <button class="btn-ghost" @click="share"><Share2 :size="16" /> {{ copied ? 'Copied' : 'Share' }}</button>
                    </div>
                </div>
                <p v-if="store.description" class="mt-4 max-w-3xl whitespace-pre-line text-[15px] leading-relaxed text-ink-600">{{ store.description }}</p>
            </div>
        </section>

        <div v-if="store.status !== 'active'" class="mt-6 rounded-2xl bg-red-50 px-4 py-3 text-sm font-medium text-red-800">This store is suspended and hidden from the public.</div>

        <!-- Category tabs -->
        <div v-if="storeCategories.length > 1" class="no-scrollbar -mx-3 mt-8 flex gap-2 overflow-x-auto px-3 sm:mx-0 sm:px-0">
            <Link :href="route('stores.show', store.slug)" class="chip" :class="!activeCategory && 'chip-active'" preserve-scroll>All</Link>
            <Link v-for="c in storeCategories" :key="c.slug" :href="route('stores.show', { store: store.slug, category: c.slug })" class="chip" :class="activeCategory === c.slug && 'chip-active'" preserve-scroll>
                {{ c.name }} <span class="opacity-60">{{ c.c }}</span>
            </Link>
        </div>

        <section class="mt-6">
            <ProductGrid v-if="products.data.length" :products="products.data" :show-store="false" />
            <EmptyState v-else :title="isOwner ? 'Your shelves are empty' : 'No items yet'" :body="isOwner ? 'Add your first product — it takes about a minute.' : 'Check back soon, this store is just getting started.'" :color="store.accent">
                <template #icon><PackageOpen :size="28" /></template>
                <Link v-if="isOwner" :href="route('sell.products.create')" class="btn-primary">Add your first item</Link>
            </EmptyState>
            <Pager :prev="products.prev_page_url" :next="products.next_page_url" />
        </section>
        <ShareSheet :show="sharing" kind="store" :id="store.id" :url="shareUrl || url" :title="store.name" :text="`Shop ${store.name} on neaby`" :card-base="route('share.store', store.slug)" :qr-url="route('qr.store', store.slug)" @close="sharing = false" />
    </AppShell>
</template>
