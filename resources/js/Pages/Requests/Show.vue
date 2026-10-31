<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ImageOff } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ request: Object, actions: Array, seo: Object });
const labels = { confirm: 'Confirm availability', propose: 'Propose terms', decline: 'Decline', fulfil: 'Mark fulfilled', cancel: 'Cancel request', accept: 'Accept & commit' };
const form = useForm({ action: '', response: '', proposed_unit_price: '' });
function act(a) {
    if (['decline', 'cancel'].includes(a) && !confirm(`${labels[a]}?`)) return;
    form.action = a;
    form.post(route('requests.act', props.request.id), { preserveScroll: true, onSuccess: () => form.reset() });
}
const typeLabel = { availability: 'Availability check', stock: 'Stock hold', delivery: 'Delivery question', question: 'Question' };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="`Request #${request.id}`" :back="route('requests.index', { tab: request.role === 'supplier' ? 'incoming' : 'outgoing' })">
        <div class="mx-auto grid max-w-2xl gap-4">
            <section class="surface p-5 text-sm">
                <div class="flex items-center gap-3">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-ink-100"><img v-if="request.product?.image" :src="request.product.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="16" /></div></div>
                    <div class="min-w-0 flex-1"><Link v-if="request.product" :href="route('products.show', request.product.slug)" class="font-medium hover:underline">{{ request.product.title }}</Link><div class="text-ink-500">{{ request.product?.available }} available now</div></div>
                    <span class="badge bg-ink-100">{{ request.status_label }}</span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3">
                    <div><dt class="text-ink-500">{{ request.role === 'supplier' ? 'Reseller' : 'Supplier' }}</dt><dd class="font-semibold">{{ request.counterparty.name }} <span class="font-normal text-ink-500">@{{ request.counterparty.username }}</span></dd></div>
                    <div><dt class="text-ink-500">Type</dt><dd class="font-semibold">{{ typeLabel[request.request_type] || request.request_type }}</dd></div>
                    <div><dt class="text-ink-500">Quantity</dt><dd class="font-semibold">{{ request.requested_quantity }}</dd></div>
                    <div><dt class="text-ink-500">Fulfilment</dt><dd class="font-semibold">{{ request.fulfilment_label }}</dd></div>
                    <div v-if="request.proposed_unit_price"><dt class="text-ink-500">Proposed unit price</dt><dd class="font-semibold">{{ request.proposed_unit_price }}</dd></div>
                    <div v-if="request.committed"><dt class="text-ink-500">Commitment</dt><dd class="font-semibold text-brand-700">Accepted{{ request.stock_reserved ? ' · stock held' : '' }}</dd></div>
                </dl>
                <p v-if="request.message" class="mt-4 rounded-xl bg-ink-50 p-3">“{{ request.message }}”</p>
                <p v-if="request.supplier_response" class="mt-2 rounded-xl bg-violet-50 p-3 text-violet-900">Supplier: {{ request.supplier_response }}</p>
                <p class="mt-2 text-xs text-ink-400">{{ request.created_at }}</p>
            </section>
            <section v-if="actions.length" class="surface grid gap-3 p-5">
                <template v-if="request.role === 'supplier'">
                    <textarea v-model="form.response" rows="2" class="input" placeholder="Reply to the reseller (optional)" />
                    <input v-if="actions.includes('propose')" v-model="form.proposed_unit_price" type="number" min="0" class="input" placeholder="Proposed unit price ₦ (for Propose terms)" />
                </template>
                <div class="flex flex-wrap gap-2"><button v-for="a in actions" :key="a" class="btn-sm" :class="['decline', 'cancel'].includes(a) ? 'btn-ghost text-red-600' : 'btn-primary'" :disabled="form.processing" @click="act(a)">{{ labels[a] }}</button></div>
                <p v-for="(e, k) in form.errors" :key="k" class="text-xs text-red-600">{{ e }}</p>
                <p v-if="actions.includes('accept')" class="hint">Accepting is a commitment: it stays valid even if a membership later lapses.</p>
            </section>
        </div>
    </AppShell>
</template>
