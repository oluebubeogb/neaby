<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ImageOff } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ product: Object, resale: Object, offer: Object, seo: Object });
const form = useForm({
    selling_price: props.offer?.selling_price ?? Math.round(props.product.price_minor / 100),
    extra_costs: props.offer?.extra_costs ?? 0,
    custom_title: props.offer?.custom_title ?? '',
    custom_description: props.offer?.custom_description ?? '',
    contact_whatsapp: props.offer?.contact_whatsapp ?? '',
    status: props.offer?.status ?? 'active',
});
const naira = (n) => '₦' + Math.round(Math.abs(n)).toLocaleString();
const m = computed(() => {
    const p = Number(form.selling_price) || 0, s = props.resale.supplier_price_minor / 100, c = Number(form.extra_costs) || 0;
    const gross = p - s;
    return { gross, contribution: gross - c, percent: p > 0 ? Math.round((gross / p) * 1000) / 10 : null };
});
const belowMin = computed(() => props.resale.min_price_minor && Number(form.selling_price) * 100 < props.resale.min_price_minor);
const submit = () => (props.offer ? form.post(route('resell.offers.update', props.offer.id)) : form.post(route('resell.offers.store', props.product.id)));
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="offer ? 'Edit offer' : 'New offer'" :back="route('resell.index')">
        <form class="mx-auto grid max-w-4xl gap-4 md:grid-cols-[1fr_300px]" @submit.prevent="submit">
            <div class="surface grid gap-3 p-5">
                <div class="flex items-center gap-3">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-ink-100"><img v-if="product.image" :src="product.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="16" /></div></div>
                    <div class="text-sm"><div class="font-medium">{{ product.title }}</div><div class="text-ink-500">Supplier {{ resale.supplier_price }} · retail {{ product.price }}<span v-if="resale.min_price"> · min {{ resale.min_price }}</span></div></div>
                </div>
                <p v-if="resale.terms" class="whitespace-pre-line rounded-xl bg-violet-50 p-3 text-sm text-violet-900">{{ resale.terms }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Your selling price ₦</label><input v-model.number="form.selling_price" type="number" min="1" class="input" /><p v-if="form.errors.selling_price" class="mt-1 text-xs text-red-600">{{ form.errors.selling_price }}</p></div>
                    <div><label class="label">Your extra costs ₦</label><input v-model.number="form.extra_costs" type="number" min="0" class="input" /><p class="hint">Delivery, packaging, ads…</p></div>
                </div>
                <div><label class="label">Custom title (optional)</label><input v-model="form.custom_title" class="input" :placeholder="product.title" /></div>
                <div><label class="label">Custom description (optional)</label><textarea v-model="form.custom_description" rows="4" class="input" :placeholder="product.description" /></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Your WhatsApp</label><input v-model="form.contact_whatsapp" class="input" type="tel" placeholder="0803…" /></div>
                    <div><label class="label">Status</label><select v-model="form.status" class="input"><option value="active">Active</option><option value="paused">Paused</option></select></div>
                </div>
                <p v-for="(e, k) in form.errors" v-show="k !== 'selling_price'" :key="k" class="text-xs text-red-600">{{ e }}</p>
            </div>
            <aside class="surface h-fit p-5 text-sm md:sticky md:top-24">
                <h2 class="section-title mb-2">Your margin</h2>
                <div class="flex justify-between"><span>Selling price</span><span>{{ naira(form.selling_price || 0) }}</span></div>
                <div class="flex justify-between text-ink-500"><span>Supplier price</span><span>− {{ resale.supplier_price }}</span></div>
                <div class="mt-2 flex justify-between border-t border-ink-100 pt-2 font-semibold" :class="m.gross < 0 ? 'text-red-600' : ''"><span>Gross margin</span><span>{{ m.gross < 0 ? '−' : '' }}{{ naira(m.gross) }}<span v-if="m.percent !== null" class="text-xs font-normal"> ({{ m.percent }}%)</span></span></div>
                <div class="flex justify-between text-ink-500"><span>After your costs</span><span :class="m.contribution < 0 ? 'text-red-600' : ''">{{ m.contribution < 0 ? '−' : '' }}{{ naira(m.contribution) }}</span></div>
                <p v-if="m.gross < 0" class="mt-2 rounded-lg bg-red-50 p-2 text-xs text-red-700">You'd sell below the supplier price.</p>
                <p v-if="belowMin" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-900">Below the supplier's minimum of {{ resale.min_price }}.</p>
                <p class="hint mt-2">Estimate only — not net profit. Stock stays with the supplier; buyers see your price only.</p>
                <button class="btn-primary mt-4 w-full" :disabled="form.processing">{{ offer ? 'Save offer' : 'Publish offer' }}</button>
                <a v-if="offer" :href="offer.url" target="_blank" class="btn-ghost mt-2 w-full">View offer page</a>
            </aside>
        </form>
    </AppShell>
</template>
