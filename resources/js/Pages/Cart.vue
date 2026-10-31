<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { AlertTriangle, ImageOff, ScanLine, Tag, Trash2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import QtyStepper from '@/Components/QtyStepper.vue';
import QrScanner from '@/Components/QrScanner.vue';
import Tip from '@/Components/Tip.vue';
import CartIcon from '@/Components/icons/CartIcon.vue';

const scanning = ref(false);

defineProps({ cart: Object, checkoutEnabled: Boolean, seo: Object });
const setQty = (item, q) => router.post(route('cart.update', item.id), { quantity: Math.max(0, q) }, { preserveScroll: true });
const remove = (item) => router.delete(route('cart.destroy', item.id), { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Cart" :back="route('home')">
        <template #actions><Tip label="Scan a QR code" side="bottom"><button class="icon-btn-lg" aria-label="Scan a QR code" @click="scanning = true"><ScanLine :size="19" /></button></Tip></template>
        <template #mobile-actions><button class="icon-btn" aria-label="Scan a QR code" @click="scanning = true"><ScanLine :size="20" /></button></template>
        <div v-if="cart.groups.length" class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_340px]">
            <div class="grid gap-4">
                <section v-for="g in cart.groups" :key="g.store.slug" class="surface p-4">
                    <Link :href="route('stores.show', g.store.slug)" class="font-display text-[15px] font-bold hover:text-brand-700">{{ g.store.name }}</Link>
                    <div class="mt-3 divide-y divide-ink-100">
                        <div v-for="i in g.items" :key="i.id" class="flex gap-3 py-3">
                            <Link :href="route('products.show', i.product.slug)" class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-ink-100">
                                <img v-if="i.product.image" :src="i.product.image" alt="" class="h-full w-full object-cover" />
                                <div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="20" /></div>
                            </Link>
                            <div class="min-w-0 flex-1">
                                <Link :href="route('products.show', i.product.slug)" class="line-clamp-2 text-sm font-medium">{{ i.product.title }}</Link>
                                <div v-if="i.options && Object.keys(i.options).length" class="mt-1 flex flex-wrap gap-1">
                                    <span v-for="(v, k) in i.options" :key="k" class="rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-medium text-ink-600">{{ k }}: {{ v }}</span>
                                </div>
                                <div class="mt-1 flex items-baseline gap-2">
                                    <span class="font-display text-[15px] font-bold">{{ i.unit_price || i.product.price }}</span>
                                    <span v-if="i.discount" class="text-xs text-ink-400 line-through">{{ i.product.price }}</span>
                                    <span v-if="i.discount" class="inline-flex items-center gap-0.5 rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800"><Tag :size="11" /> {{ i.discount }}</span>
                                </div>
                                <p v-if="!i.ok" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-red-600"><AlertTriangle :size="12" /> {{ i.available ? `Only ${i.available} available` : 'Not available for checkout' }}</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <QtyStepper small :model-value="i.quantity" :max="Math.max(i.available || 0, 1)" @change="(n) => n !== i.quantity && setQty(i, n)" />
                                    <span class="ml-auto text-sm font-semibold">{{ i.line_total }}</span>
                                    <button class="icon-btn h-8 w-8 text-red-600" aria-label="Remove" @click="remove(i)"><Trash2 :size="14" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex justify-between border-t border-ink-100 pt-3 text-sm text-ink-600"><span>Subtotal · delivery from {{ g.delivery }}</span><span class="font-semibold text-ink-900">{{ g.subtotal }}</span></div>
                </section>
            </div>
            <aside class="surface h-fit p-5 lg:sticky lg:top-24">
                <div class="flex justify-between text-sm"><span>Items ({{ cart.count }})</span><span>{{ cart.subtotal }}</span></div>
                <div class="mt-1 flex justify-between text-sm text-ink-500"><span>Delivery</span><span>{{ cart.delivery }}</span></div>
                <div class="mt-3 flex justify-between border-t border-ink-100 pt-3 font-display text-base font-extrabold"><span>Total</span><span>{{ cart.total_delivery }}</span></div>
                <p v-if="cart.pickup_ok" class="hint mt-1">Or {{ cart.total_pickup }} with pickup.</p>
                <Link v-if="checkoutEnabled && cart.all_ok" :href="route('checkout.show')" class="btn-primary btn-lg mt-4 w-full">Checkout</Link>
                <button v-else class="btn-primary btn-lg mt-4 w-full" disabled>{{ checkoutEnabled ? 'Fix items above' : 'Checkout unavailable' }}</button>
                <p class="hint mt-2">One order is created per store. Each seller ships separately.</p>
            </aside>
        </div>
        <EmptyState v-else title="Your cart is empty" body="Look for the “Buy now” button on listings that support neaby checkout.">
            <template #icon><CartIcon :size="28" /></template>
            <Link :href="route('home')" class="btn-primary">Start shopping</Link>
            <button class="btn-ghost" @click="scanning = true"><ScanLine :size="17" /> Scan a QR code</button>
        </EmptyState>
        <QrScanner :show="scanning" @close="scanning = false" />
    </AppShell>
</template>
