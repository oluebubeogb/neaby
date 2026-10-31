<script setup>
import { computed, ref } from 'vue';
import { ImageOff, MapPin, MessageCircle, Share2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import ShareSheet from '@/Components/ShareSheet.vue';

const props = defineProps({ offer: Object, isReseller: Boolean, seo: Object });
const active = ref(0);
const sharing = ref(false);
const wa = computed(() => {
    if (!props.offer.whatsapp) return null;
    let n = props.offer.whatsapp.replace(/[^0-9]/g, '');
    if (n.startsWith('0')) n = '234' + n.slice(1);
    return `https://wa.me/${n}?text=${encodeURIComponent(`Hi! I'm interested in “${props.offer.title}” (${props.offer.price}): ${props.offer.url}`)}`;
});
function xsrf() { const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
function enquire() {
    try { fetch(route('offers.enquire', props.offer.slug), { method: 'POST', credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); } catch (_) {}
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :back="route('home')" title="" wide>
        <template #mobile-actions><button class="icon-btn" aria-label="Share" @click="sharing = true"><Share2 :size="19" /></button></template>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-10">
            <section class="-mx-3 sm:mx-0">
                <div class="overflow-hidden bg-ink-100 sm:rounded-[2rem]">
                    <img v-if="offer.images.length" :src="offer.images[active]?.url" :alt="offer.title" class="aspect-square w-full object-cover" />
                    <div v-else class="flex aspect-square items-center justify-center text-ink-300"><ImageOff :size="40" /></div>
                </div>
                <div v-if="offer.images.length > 1" class="mt-3 flex gap-2 px-3 sm:px-0"><button v-for="(img, i) in offer.images" :key="img.id" class="h-16 w-16 overflow-hidden rounded-2xl ring-2" :class="i === active ? 'ring-brand-500' : 'ring-transparent opacity-70'" @click="active = i"><img :src="img.url" alt="" class="h-full w-full object-cover" /></button></div>
            </section>
            <section>
                <div class="flex flex-wrap gap-2 text-xs"><span v-if="offer.category" class="badge bg-ink-100 text-ink-700">{{ offer.category }}</span><span class="badge bg-ink-100 capitalize text-ink-700">{{ offer.condition }}</span><span class="badge" :class="offer.in_stock ? 'bg-brand-100 text-brand-800' : 'bg-red-100 text-red-700'">{{ offer.in_stock ? 'In stock' : 'Sold out' }}</span></div>
                <h1 class="mt-3 font-display text-2xl font-extrabold leading-tight md:text-[2rem]">{{ offer.title }}</h1>
                <div class="mt-3 font-display text-3xl font-extrabold">{{ offer.price }}</div>
                <div v-if="offer.city || offer.state" class="mt-2 inline-flex items-center gap-1 text-sm text-ink-500"><MapPin :size="14" /> {{ [offer.city, offer.state].filter(Boolean).join(', ') }}</div>
                <div class="mt-6 grid gap-2 sm:grid-cols-[1fr_auto]">
                    <a v-if="wa && offer.live" :href="wa" target="_blank" rel="noopener" class="btn-primary btn-lg" @click="enquire"><MessageCircle :size="18" /> Chat on WhatsApp</a>
                    <button class="btn-ghost btn-lg" @click="sharing = true"><Share2 :size="18" /> Share</button>
                </div>
                <p class="mt-2 text-xs text-ink-400">Offered by {{ offer.reseller?.name || 'an independent reseller' }} on neaby. Arrange payment and delivery with them directly.</p>
                <div class="mt-8"><h2 class="section-title mb-3">About this item</h2><div class="whitespace-pre-line text-[15px] leading-relaxed text-ink-700">{{ offer.description }}</div></div>
            </section>
        </div>
        <ShareSheet :show="sharing" kind="offer" :id="offer.id" :url="offer.url" :title="offer.title" :text="`${offer.title} — ${offer.price}`" :card-base="route('share.offer', offer.slug)" @close="sharing = false" />
    </AppShell>
</template>
