<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ImageOff, Save, Search } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ products: Array, categories: Array, q: String, seo: Object });
const rows = ref(props.products.map((p) => ({ ...p })));
const original = Object.fromEntries(props.products.map((p) => [p.id, JSON.stringify(p)]));
const dirty = computed(() => rows.value.filter((r) => JSON.stringify(r) !== original[r.id]));
const search = ref(props.q || '');
const form = useForm({ rows: [] });
function save() {
    form.rows = dirty.value.map((r) => ({ id: r.id, price: r.price, quantity: r.quantity, category_id: r.category_id, status: r.status, checkout_enabled: r.checkout_enabled ? 1 : 0 }));
    form.post(route('sell.bulk.update'), { preserveScroll: true });
}
const doSearch = () => router.get(route('sell.bulk.index'), search.value ? { q: search.value } : {}, { preserveState: false });
const setAll = (k, v) => rows.value.forEach((r) => (r[k] = v));
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Bulk edit" :back="route('sell.dashboard')" wide>
        <template #actions><button class="btn-primary" :disabled="!dirty.length || form.processing" @click="save"><Save :size="16" /> Save {{ dirty.length || '' }}</button></template>
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <form class="relative flex-1" @submit.prevent="doSearch"><Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" /><input v-model="search" class="input pl-9" placeholder="Filter by title" /></form>
            <button class="btn-ghost btn-sm" @click="setAll('checkout_enabled', true)">Checkout on for all</button>
            <button class="btn-primary btn-sm md:hidden" :disabled="!dirty.length || form.processing" @click="save">Save {{ dirty.length || '' }}</button>
        </div>
        <p v-for="(e, k) in form.errors" :key="k" class="mb-1 text-xs text-red-600">{{ e }}</p>
        <div class="surface overflow-x-auto">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="text-left text-xs text-ink-500"><tr><th class="p-3">Product</th><th class="p-3">Price (₦)</th><th class="p-3">Qty</th><th class="p-3">Category</th><th class="p-3">Status</th><th class="p-3">Checkout</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="r in rows" :key="r.id" :class="JSON.stringify(r) !== original[r.id] ? 'bg-amber-50/60' : ''">
                        <td class="p-2"><div class="flex items-center gap-2"><div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-ink-100"><img v-if="r.image" :src="r.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="14" /></div></div><span class="line-clamp-2 max-w-[260px]">{{ r.title }}</span></div></td>
                        <td class="p-2"><input v-model.number="r.price" type="number" min="1" class="input w-28 py-1.5" /></td>
                        <td class="p-2"><input v-model.number="r.quantity" type="number" :min="r.reserved" class="input w-20 py-1.5" /><div v-if="r.reserved" class="text-[11px] text-ink-400">{{ r.reserved }} reserved</div></td>
                        <td class="p-2"><select v-model="r.category_id" class="input w-40 py-1.5"><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></td>
                        <td class="p-2"><select v-model="r.status" class="input w-32 py-1.5"><option value="draft">Draft</option><option value="published" :disabled="!r.has_photo">Publish</option><option value="paused">Paused</option><option value="archived">Archived</option><option v-if="!['draft','published','paused','archived'].includes(r.status)" :value="r.status">{{ r.status }}</option></select></td>
                        <td class="p-2"><input v-model="r.checkout_enabled" type="checkbox" class="h-5 w-5 rounded text-brand-600" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="hint mt-2">Publishing needs at least one photo and may go to review. Quantity can't go below what's reserved by pending orders.</p>
    </AppShell>
</template>
