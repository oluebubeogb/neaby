<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { BadgeCheck, Instagram, MapPin, Navigation, PackageOpen, Pencil, Plus, Share2, X } from 'lucide-vue-next';
import ProfileHero from '@/Components/ProfileHero.vue';
import ReachOut from '@/Components/ReachOut.vue';
import Tip from '@/Components/Tip.vue';
import WhatsAppLine from '@/Components/icons/WhatsAppLine.vue';
import { waLink } from '@/composables/wa';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import ProductGrid from '@/Components/ProductGrid.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';
import ShareSheet from '@/Components/ShareSheet.vue';

const props = defineProps({ store: Object, isOwner: Boolean, products: Object, storeCategories: Array, activeCategory: String, shareUrl: String, seo: Object });

const url = computed(() => route('stores.show', props.store.slug));
const wa = computed(() => waLink(props.store.whatsapp, `Hi ${props.store.name}! I found your store on neaby: ${url.value}`));
const sharing = ref(false);
function share() { sharing.value = true; }
const directions = computed(() => (props.store.location ? `https://www.google.com/maps/dir/?api=1&destination=${props.store.location.lat},${props.store.location.lng}` : null));
const viewing = ref(null); // index of the shop photo open full-screen
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell wide>
        <!-- Hero -->
        <ProfileHero :cover="store.cover" :accent="store.accent" :avatar="store.logo" :initials="store.initials" square :name="store.name" :handle="store.owner ? `by @${store.owner.username}` : null" :tagline="store.tagline">
            <template #badge><BadgeCheck :size="18" class="text-brand-500" aria-label="Store" /></template>
            <template #actions>
                <template v-if="isOwner">
                    <Tip label="Add item"><Link :href="route('sell.products.create')" class="icon-btn-primary" aria-label="Add item"><Plus :size="19" /></Link></Tip>
                    <Tip label="Edit store"><Link :href="route('sell.store.edit')" class="icon-btn-lg" aria-label="Edit store"><Pencil :size="17" /></Link></Tip>
                </template>
                <template v-else>
                    <ReachOut :store="store.id" :name="store.name" variant="button" :draft="`Hi ${store.name}! I found your store on neaby.`" />
                    <Tip v-if="wa" label="WhatsApp"><a :href="wa" target="_blank" rel="noopener" class="icon-btn-lg" aria-label="WhatsApp"><WhatsAppLine :size="19" /></a></Tip>
                </template>
                <Tip v-if="store.instagram" label="Instagram"><a :href="`https://instagram.com/${store.instagram.replace('@', '')}`" target="_blank" rel="noopener" class="icon-btn-lg" aria-label="Instagram"><Instagram :size="18" /></a></Tip>
                <Tip label="Share"><button class="icon-btn-lg" aria-label="Share" @click="share"><Share2 :size="18" /></button></Tip>
            </template>
            <template #meta>
                <span v-if="store.city || store.state" class="inline-flex items-center gap-1"><MapPin :size="13" /> {{ [store.city, store.state].filter(Boolean).join(', ') }}</span>
                <a v-if="store.location" :href="directions" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-semibold text-brand-700 hover:underline"><Navigation :size="13" /> {{ store.travel || 'Directions' }}</a>
                <span><b class="font-bold text-ink-800">{{ store.products_count }}</b> items</span>
                <span>Joined {{ store.joined }}</span>
            </template>
            <template #description>
                <p v-if="store.description" class="mt-1 line-clamp-4 max-w-3xl whitespace-pre-line text-[13.5px] leading-relaxed text-ink-600">{{ store.description }}</p>
            </template>
        </ProfileHero>

        <!-- Shop photos (live-camera captures of the shop front + surroundings) -->
        <section v-if="store.photos?.length" class="no-scrollbar -mx-3 mt-6 flex gap-2 overflow-x-auto px-3 sm:mx-0 sm:px-0" aria-label="Shop photos">
            <button v-for="(ph, i) in store.photos" :key="ph.id" class="h-28 w-36 shrink-0 overflow-hidden rounded-2xl bg-ink-100 ring-1 ring-ink-200/60 transition hover:opacity-90 md:h-36 md:w-48" :aria-label="`Shop photo ${i + 1}`" @click="viewing = i">
                <img :src="ph.url" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async" />
            </button>
        </section>
        <Teleport to="body">
            <div v-if="viewing !== null" class="fixed inset-0 z-[70] flex items-center justify-center bg-ink-950/90 p-4 backdrop-blur" role="dialog" aria-modal="true" @click.self="viewing = null" @keydown.esc="viewing = null">
                <img :src="store.photos[viewing].url" alt="" class="max-h-full max-w-full rounded-2xl object-contain" />
                <button class="immersive-btn absolute right-4 top-[calc(env(safe-area-inset-top)+16px)] h-10 w-10" aria-label="Close" @click="viewing = null"><X :size="20" /></button>
            </div>
        </Teleport>

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
