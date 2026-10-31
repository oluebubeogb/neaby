<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Check, Instagram, MapPin, Pencil, Share2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import StoreChip from '@/Components/StoreChip.vue';
import ProductGrid from '@/Components/ProductGrid.vue';
import ProfileHero from '@/Components/ProfileHero.vue';
import ReachOut from '@/Components/ReachOut.vue';
import Tip from '@/Components/Tip.vue';
import WhatsAppLine from '@/Components/icons/WhatsAppLine.vue';
import { waLink } from '@/composables/wa';

const props = defineProps({ profile: Object, store: Object, products: { type: Array, default: () => [] }, isSelf: Boolean, url: String, seo: Object });

const wa = computed(() => waLink(props.store?.whatsapp, `Hi ${props.profile.name}! I found you on neaby: ${props.url}`));
const copied = ref(false);
async function share() {
    const data = { title: `${props.profile.name} on neaby`, url: props.url };
    try {
        if (navigator.share) return await navigator.share(data);
        await navigator.clipboard.writeText(props.url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (_) { /* dismissed */ }
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell wide>
        <ProfileHero :cover="store?.cover" :accent="store?.accent || '#16b26a'" :avatar="profile.avatar" :initials="profile.initials" :name="profile.name" :handle="`@${profile.username}`" :tagline="profile.bio">
            <template #actions>
                <Tip v-if="isSelf" label="Edit profile"><Link :href="route('profile.edit')" class="icon-btn-lg" aria-label="Edit profile"><Pencil :size="17" /></Link></Tip>
                <template v-else>
                    <ReachOut :user="profile.username" :name="profile.name.split(' ')[0]" variant="button" />
                    <Tip v-if="wa" label="WhatsApp"><a :href="wa" target="_blank" rel="noopener" class="icon-btn-lg" aria-label="WhatsApp"><WhatsAppLine :size="19" /></a></Tip>
                </template>
                <Tip v-if="store?.instagram" label="Instagram"><a :href="`https://instagram.com/${store.instagram.replace('@', '')}`" target="_blank" rel="noopener" class="icon-btn-lg" aria-label="Instagram"><Instagram :size="18" /></a></Tip>
                <Tip :label="copied ? 'Link copied' : 'Share'"><button class="icon-btn-lg" aria-label="Share" @click="share"><component :is="copied ? Check : Share2" :size="18" /></button></Tip>
            </template>
            <template #meta>
                <span v-if="profile.city || profile.state" class="inline-flex items-center gap-1"><MapPin :size="13" /> {{ [profile.city, profile.state].filter(Boolean).join(', ') }}</span>
                <span>Joined {{ profile.joined }}</span>
                <span v-if="store"><b class="font-bold text-ink-800">{{ store.products_count }}</b> listings</span>
            </template>
        </ProfileHero>

        <section v-if="store" class="mt-8">
            <h2 class="section-title mb-3">Store</h2>
            <StoreChip :store="store" class="max-w-xl" />
        </section>

        <section v-if="products.length" class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="section-title">Latest listings</h2>
                <Link v-if="store" :href="route('stores.show', store.slug)" class="text-sm font-semibold text-brand-700 hover:underline">See all →</Link>
            </div>
            <ProductGrid :products="products" :show-store="false" dense />
        </section>
        <p v-else-if="!store" class="mt-10 text-center text-sm text-ink-400">{{ isSelf ? 'You haven’t opened a store yet.' : `${profile.name.split(' ')[0]} isn’t selling anything yet.` }}</p>
    </AppShell>
</template>
