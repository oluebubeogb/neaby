<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CheckCircle2, ImageOff, MapPin, Phone } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import OrderStatus from '@/Components/OrderStatus.vue';

const props = defineProps({ order: Object, canCancel: Boolean, seo: Object });
const labels = { processing: 'Mark processing', shipped: 'Mark shipped', ready_for_pickup: 'Ready for pickup', delivered: 'Mark delivered', cancelled: 'Cancel & refund' };
const nextSteps = computed(() => (props.order.next || []).filter((s) => labels[s] && (s !== 'cancelled' || props.canCancel)));
const form = useForm({ status: '', note: '' });
function move(s) {
    if (s === 'cancelled' && !confirm('Cancel this order? Paid orders are queued for a refund to the buyer and stock is returned.')) return;
    form.status = s;
    form.post(route('sell.orders.status', props.order.id), { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="`Order #${order.number}`" :back="route('sell.orders.index')">
        <div class="mx-auto grid max-w-4xl gap-4 lg:grid-cols-[1fr_300px]">
            <section class="surface p-5">
                <div class="flex items-center gap-2"><OrderStatus :status="order.status" :label="order.status_label" /><span class="text-sm text-ink-500">{{ order.created_at }}</span></div>
                <div class="mt-4 divide-y divide-ink-100">
                    <div v-for="i in order.items" :key="i.id" class="flex items-center gap-3 py-3">
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-ink-100"><img v-if="i.image" :src="i.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="16" /></div></div>
                        <div class="min-w-0 flex-1 text-sm"><div class="font-medium">{{ i.title }}</div><div class="text-ink-500">{{ i.quantity }} × {{ i.unit_price }}</div></div>
                        <div class="font-semibold">{{ i.line_total }}</div>
                    </div>
                </div>
                <div class="mt-2 border-t border-ink-100 pt-3 text-sm"><div class="flex justify-between"><span>Subtotal</span><span>{{ order.subtotal }}</span></div><div class="flex justify-between text-ink-500"><span>{{ order.delivery_method }}</span><span>{{ order.delivery }}</span></div><div class="flex justify-between font-display text-lg font-extrabold"><span>Total</span><span>{{ order.total }}</span></div></div>
                <div v-if="nextSteps.length" class="mt-5 grid gap-2 border-t border-ink-100 pt-4">
                    <label class="label">Note to buyer (optional — tracking number, pickup address…)</label>
                    <input v-model="form.note" class="input" maxlength="300" />
                    <div class="flex flex-wrap gap-2">
                        <button v-for="s in nextSteps" :key="s" class="btn-sm" :class="s === 'cancelled' ? 'btn-ghost text-red-600' : 'btn-primary'" :disabled="form.processing" @click="move(s)">{{ labels[s] }}</button>
                    </div>
                    <p v-for="(e, k) in form.errors" :key="k" class="text-xs text-red-600">{{ e }}</p>
                </div>
                <p v-if="order.tracking_note" class="mt-3 text-sm text-ink-600">Last note: {{ order.tracking_note }}</p>
            </section>
            <aside class="grid h-fit gap-4">
                <section class="surface p-5 text-sm">
                    <h2 class="section-title mb-2">{{ order.delivery_method === 'pickup' ? 'Pickup by' : 'Deliver to' }}</h2>
                    <div class="font-semibold">{{ order.contact_name }}</div>
                    <a :href="`tel:${order.contact_phone}`" class="flex items-center gap-1.5 text-brand-700"><Phone :size="13" /> {{ order.contact_phone }}</a>
                    <div v-if="order.address" class="mt-1 flex items-start gap-1.5 text-ink-600"><MapPin :size="13" class="mt-0.5" /> {{ order.address }}, {{ order.city }}, {{ order.state }}</div>
                    <p v-if="order.note" class="mt-2 text-ink-500">“{{ order.note }}”</p>
                </section>
                <section class="surface p-5 text-sm">
                    <h2 class="section-title mb-2">Progress</h2>
                    <div v-for="(t, k) in order.timeline" :key="k" class="flex items-center gap-2 py-0.5"><CheckCircle2 :size="15" class="text-brand-600" /><span class="flex-1">{{ t.label }}</span><span class="text-xs text-ink-400">{{ t.at }}</span></div>
                    <div v-for="(r, k) in order.refunds" :key="'r' + k" class="mt-2 text-ink-600">Refund {{ r.amount }} — {{ r.status }}</div>
                </section>
            </aside>
        </div>
    </AppShell>
</template>
