<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { BadgeCheck, Clock, ImageOff, Lock, Tag } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import QtyStepper from '@/Components/QtyStepper.vue';
import OptionPicker from '@/Components/OptionPicker.vue';
import CartIcon from '@/Components/icons/CartIcon.vue';
import { requireLogin } from '@/composables/auth';

const props = defineProps({ qr: Object, product: Object, canBuy: Boolean, maxQty: Number, seo: Object });
const page = usePage();

const max = computed(() => Math.max(1, props.maxQty || 1));
const qty = ref(Math.min(max.value, props.qr.default_quantity || 1));
const options = computed(() => props.product.options || []);
const chosen = ref({ ...(props.qr.preset_options || {}) });
const optionError = ref(null);
const busy = ref(false);
const errors = computed(() => page.props.errors || {});
const soldOut = computed(() => (props.maxQty || 0) < 1);

function add() {
    if (!page.props.auth?.user) return requireLogin();
    if (props.qr.allow_adjust) {
        const missing = options.value.find((o) => !chosen.value[o.name]);
        if (missing) { optionError.value = `Choose a ${missing.name.toLowerCase()}.`; return; }
    }
    optionError.value = null;
    busy.value = true;
    router.post(route('q.add', props.qr.code), { quantity: qty.value, options: chosen.value }, { onFinish: () => (busy.value = false) });
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Scanned code" :back="route('home')">
        <div class="mx-auto max-w-md">
            <div class="surface overflow-hidden">
                <div class="relative aspect-[4/3] bg-ink-100">
                    <img v-if="product.images?.[0]" :src="product.images[0].url" :alt="product.title" class="h-full w-full object-cover" fetchpriority="high" />
                    <div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="36" /></div>
                    <span v-if="qr.discount && qr.redeemable" class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-brand-500 px-3 py-1 text-xs font-bold text-white shadow-glow"><Tag :size="13" /> {{ qr.discount }}</span>
                    <span v-else-if="!qr.redeemable" class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-ink-900/80 px-3 py-1 text-xs font-bold text-white backdrop-blur"><Clock :size="13" /> {{ qr.expired ? 'Offer expired' : 'Offer ended' }}</span>
                </div>
                <div class="p-4">
                    <Link v-if="product.store" :href="route('stores.show', product.store.slug)" class="inline-flex items-center gap-2 text-[13px] font-semibold text-ink-700">
                        <Avatar :src="product.store.logo" :initials="product.store.initials" :accent="product.store.accent" :size="24" rounded="rounded-lg" /> {{ product.store.name }} <BadgeCheck :size="14" class="text-brand-500" />
                    </Link>
                    <Link :href="route('products.show', product.slug)" class="mt-2 block font-display text-lg font-bold leading-snug hover:underline">{{ product.title }}</Link>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="font-display text-xl font-extrabold text-ink-900">{{ qr.unit_price }}</span>
                        <span v-if="qr.redeemable && qr.discount" class="text-sm text-ink-400 line-through">{{ product.price }}</span>
                    </div>
                    <p v-if="qr.label" class="mt-1 text-[13px] text-ink-500">{{ qr.label }}</p>
                    <p v-if="qr.expires_label && qr.redeemable" class="mt-1 text-xs text-amber-700">Offer ends {{ qr.expires_label }}</p>

                    <template v-if="canBuy && !soldOut">
                        <div v-if="qr.allow_adjust" class="mt-4 grid gap-4">
                            <OptionPicker v-model="chosen" :options="options" :error="optionError || errors.options" />
                            <div class="flex items-center gap-3">
                                <QtyStepper v-model="qty" :max="max" :disabled="busy" />
                                <span class="text-xs text-ink-400">{{ max }} in stock</span>
                            </div>
                        </div>
                        <div v-else class="mt-4 flex items-center gap-2 rounded-2xl bg-ink-50 px-3 py-2 text-[13px] text-ink-600">
                            <Lock :size="14" /> {{ qr.default_quantity }} × {{ Object.entries(qr.preset_options || {}).map(([k, v]) => `${k} ${v}`).join(', ') || 'as set by the seller' }}
                        </div>
                        <p v-if="errors.quantity" class="mt-2 text-xs font-medium text-red-600">{{ errors.quantity }}</p>
                        <button class="btn-primary btn-lg mt-4 w-full" :disabled="busy" @click="add"><CartIcon :size="19" /> Add to cart</button>
                    </template>
                    <p v-else-if="soldOut" class="mt-4 rounded-2xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700">Sold out right now.</p>
                    <Link v-else :href="route('products.show', product.slug)" class="btn-ghost btn-lg mt-4 w-full">View listing</Link>
                </div>
            </div>
        </div>
    </AppShell>
</template>
