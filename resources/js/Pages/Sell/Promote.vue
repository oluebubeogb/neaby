<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Megaphone } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import ProductCard from '@/Components/ProductCard.vue';

const props = defineProps({ product: Object, options: Array, enabled: Boolean, isPublic: Boolean, seo: Object });
const days = ref(props.options[1]?.days || props.options[0]?.days);
const busy = ref(false);
const start = () => { busy.value = true; router.post(route('sell.promote.start', props.product.id), { days: days.value }, { onFinish: () => (busy.value = false) }); };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Promote listing" :back="route('sell.insights')">
        <div class="mx-auto grid max-w-3xl gap-5 md:grid-cols-[220px_1fr]">
            <ProductCard :product="{ ...product, sponsored: true, sp: null }" />
            <div class="surface p-5">
                <h2 class="flex items-center gap-2 font-display text-lg font-bold"><Megaphone :size="18" /> Boost in For You</h2>
                <p class="mt-1 text-sm text-ink-500">Your listing appears more often in the feed, always marked <b>Sponsored</b>. Ranking otherwise stays fair — quality and relevance still matter.</p>
                <div v-if="!enabled" class="mt-4 rounded-xl bg-ink-50 p-3 text-sm">Sponsored listings are not enabled on this site yet.</div>
                <div v-else-if="!isPublic" class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Publish this listing before promoting it.</div>
                <template v-else>
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <button v-for="o in options" :key="o.days" class="rounded-2xl border p-3 text-center" :class="days === o.days ? 'border-brand-500 bg-brand-50' : 'border-ink-200'" @click="days = o.days"><div class="font-display text-xl font-extrabold">{{ o.days }}d</div><div class="text-xs text-ink-500">{{ o.price }}</div></button>
                    </div>
                    <button class="btn-primary btn-lg mt-4 w-full" :disabled="busy" @click="start">Pay {{ options.find((o) => o.days === days)?.price }}</button>
                </template>
            </div>
        </div>
    </AppShell>
</template>
