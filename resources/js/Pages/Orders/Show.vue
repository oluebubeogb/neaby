<script setup>
import { Link, router } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard, ImageOff, MapPin, Phone, XCircle } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import OrderStatus from '@/Components/OrderStatus.vue';

const props = defineProps({ order: Object, canCancel: Boolean, canConfirm: Boolean, payUrl: String, seo: Object, seller: Boolean });
function cancel() {
    const reason = prompt('Why are you cancelling? (optional)');
    if (reason === null) return;
    router.post(route('orders.cancel', props.order.id), { reason }, { preserveScroll: true });
}
const confirmReceipt = () => confirm('Confirm you received this order?') && router.post(route('orders.confirm', props.order.id), {}, { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="`Order #${order.number}`" :back="route('orders.index')">
        <div class="mx-auto grid max-w-4xl gap-4 lg:grid-cols-[1fr_300px]">
            <div class="grid gap-4">
                <section class="surface p-5">
                    <div class="flex flex-wrap items-center gap-2"><OrderStatus :status="order.status" :label="order.status_label" /><span class="text-sm text-ink-500">{{ order.created_at }}</span></div>
                    <div class="mt-4 divide-y divide-ink-100">
                        <div v-for="i in order.items" :key="i.id" class="flex items-center gap-3 py-3">
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-ink-100"><img v-if="i.image" :src="i.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="16" /></div></div>
                            <div class="min-w-0 flex-1"><Link v-if="i.product_slug" :href="route('products.show', i.product_slug)" class="line-clamp-2 text-sm font-medium hover:underline">{{ i.title }}</Link><span v-else class="text-sm font-medium">{{ i.title }}</span><div class="text-xs text-ink-500">{{ i.quantity }} × {{ i.unit_price }}</div></div>
                            <div class="font-semibold">{{ i.line_total }}</div>
                        </div>
                    </div>
                    <div class="mt-2 grid gap-1 border-t border-ink-100 pt-3 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span>{{ order.subtotal }}</span></div>
                        <div class="flex justify-between text-ink-500"><span>{{ order.delivery_method === 'pickup' ? 'Pickup' : 'Delivery' }}</span><span>{{ order.delivery }}</span></div>
                        <div class="flex justify-between font-display text-lg font-extrabold"><span>Total</span><span>{{ order.total }}</span></div>
                    </div>
                </section>
                <section v-if="order.tracking_note || order.cancel_reason || order.refunds?.length" class="surface p-5 text-sm">
                    <p v-if="order.tracking_note"><span class="font-semibold">Seller note:</span> {{ order.tracking_note }}</p>
                    <p v-if="order.cancel_reason" class="mt-1"><span class="font-semibold">Cancelled:</span> {{ order.cancel_reason }}</p>
                    <div v-for="(r, k) in order.refunds" :key="k" class="mt-1">Refund {{ r.amount }} — <span class="capitalize">{{ r.status }}</span> ({{ r.at }})</div>
                </section>
                <slot />
            </div>
            <aside class="grid h-fit gap-4">
                <section class="surface p-5 text-sm">
                    <h2 class="section-title mb-2">Progress</h2>
                    <ol class="grid gap-2">
                        <li v-for="(t, k) in order.timeline" :key="k" class="flex items-center gap-2"><CheckCircle2 :size="16" class="text-brand-600" /><span class="flex-1">{{ t.label }}</span><span class="text-xs text-ink-400">{{ t.at }}</span></li>
                    </ol>
                </section>
                <section class="surface p-5 text-sm">
                    <h2 class="section-title mb-2">{{ order.delivery_method === 'pickup' ? 'Pickup contact' : 'Deliver to' }}</h2>
                    <div class="font-semibold">{{ order.contact_name }}</div>
                    <div class="flex items-center gap-1.5 text-ink-600"><Phone :size="13" /> {{ order.contact_phone }}</div>
                    <div v-if="order.address" class="mt-1 flex items-start gap-1.5 text-ink-600"><MapPin :size="13" class="mt-0.5" /> {{ order.address }}, {{ order.city }}, {{ order.state }}</div>
                    <p v-if="order.note" class="mt-2 text-ink-500">“{{ order.note }}”</p>
                    <Link v-if="order.store && !seller" :href="route('stores.show', order.store.slug)" class="btn-ghost btn-sm mt-3">Seller: {{ order.store.name }}</Link>
                    <div v-if="seller && order.buyer" class="mt-2 text-ink-500">Buyer: @{{ order.buyer.username }}</div>
                </section>
                <div v-if="!seller" class="grid gap-2">
                    <a v-if="payUrl" :href="payUrl" class="btn-primary"><CreditCard :size="16" /> Complete payment</a>
                    <button v-if="canConfirm" class="btn-dark" @click="confirmReceipt"><CheckCircle2 :size="16" /> I received it</button>
                    <button v-if="canCancel" class="btn-ghost text-red-600" @click="cancel"><XCircle :size="16" /> Cancel order</button>
                </div>
            </aside>
        </div>
    </AppShell>
</template>
