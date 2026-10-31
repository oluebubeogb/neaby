<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Check, Copy, Download, Pause, Play, Plus, QrCode, Trash2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Tip from '@/Components/Tip.vue';
import Modal from '@/Components/Modal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import OptionPicker from '@/Components/OptionPicker.vue';

const props = defineProps({ codes: Array, products: Array, limits: Object, seo: Object });

const creating = ref(false);
const preview = ref(null);
const copied = ref(null);
const form = useForm({
    product_id: props.products[0]?.id || '',
    label: '',
    discount_type: '',
    discount_value: '',
    default_quantity: 1,
    preset_options: {},
    allow_adjust: true,
    expires_at: '',
    max_uses: '',
});
const product = computed(() => props.products.find((p) => p.id === Number(form.product_id)));
watch(() => form.product_id, () => (form.preset_options = {}));

const estimate = computed(() => {
    const p = product.value;
    const v = Number(form.discount_value);
    if (!p || !form.discount_type || !v) return null;
    const minor = form.discount_type === 'percent' ? Math.round(p.price_minor * (1 - v / 100)) : Math.max(0, p.price_minor - Math.round(v * 100));
    return '₦' + (minor / 100).toLocaleString('en-NG', { maximumFractionDigits: 2 });
});
const minExpiry = computed(() => { const d = new Date(Date.now() + 5 * 60000); d.setSeconds(0, 0); return new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16); });

function submit() {
    form.transform((d) => ({
        ...d,
        discount_type: d.discount_type || null,
        discount_value: d.discount_type ? d.discount_value : null,
        expires_at: d.expires_at ? new Date(d.expires_at).toISOString() : null,
        max_uses: d.max_uses || null,
    })).post(route('sell.qr.store'), { preserveScroll: true, onSuccess: () => { creating.value = false; form.reset(); } });
}
function toggle(c) { router.post(route('sell.qr.update', c.code), { active: !c.active }, { preserveScroll: true }); }
function remove(c) { if (confirm('Delete this QR code? Printed copies will stop working.')) router.delete(route('sell.qr.destroy', c.code), { preserveScroll: true }); }
async function copy(c) { try { await navigator.clipboard.writeText(c.url); copied.value = c.id; setTimeout(() => (copied.value = null), 1800); } catch (_) {} }
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="QR codes" :back="route('sell.dashboard')">
        <template #actions><button class="btn-primary" :disabled="!products.length" @click="creating = true"><Plus :size="17" /> New code</button></template>
        <template #mobile-actions><button class="icon-btn" aria-label="New QR code" :disabled="!products.length" @click="creating = true"><Plus :size="20" /></button></template>

        <p class="mb-4 max-w-2xl text-[13.5px] text-ink-600">Print a code for a poster, packaging or your counter. Scanning it adds the item to the buyer’s cart — with an optional discount, preset size and quantity, and an expiry.</p>

        <div v-if="codes.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="c in codes" :key="c.id" class="surface flex gap-3 p-3" :class="!c.redeemable ? 'opacity-75' : ''">
                <button class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white ring-1 ring-ink-100" aria-label="Preview QR code" @click="preview = c">
                    <img :src="c.png" alt="QR code" class="h-full w-full object-contain p-1" loading="lazy" />
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[14px] font-semibold">{{ c.label || c.product?.title }}</div>
                            <div v-if="c.label" class="truncate text-xs text-ink-500">{{ c.product?.title }}</div>
                        </div>
                        <span class="badge shrink-0" :class="c.redeemable ? 'bg-brand-100 text-brand-800' : c.expired ? 'bg-amber-100 text-amber-800' : 'bg-ink-100 text-ink-600'">{{ c.redeemable ? 'Live' : c.expired ? 'Expired' : !c.active ? 'Off' : 'Used up' }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-ink-500">
                        <span v-if="c.discount" class="font-semibold text-brand-700">{{ c.discount }} → {{ c.price_after }}</span>
                        <span>{{ c.default_quantity }}× <template v-if="c.preset_options && Object.keys(c.preset_options).length">· {{ Object.values(c.preset_options).join(', ') }}</template></span>
                        <span v-if="!c.allow_adjust">locked</span>
                        <span>{{ c.scans }} scans · {{ c.uses }}{{ c.max_uses ? `/${c.max_uses}` : '' }} used</span>
                        <span v-if="c.expires_label">ends {{ c.expires_label }}</span>
                    </div>
                    <div class="mt-2 flex items-center gap-1">
                        <Tip :label="copied === c.id ? 'Copied' : 'Copy link'"><button class="icon-btn h-8 w-8" aria-label="Copy link" @click="copy(c)"><component :is="copied === c.id ? Check : Copy" :size="15" /></button></Tip>
                        <Tip label="Download PNG"><a :href="c.png" :download="`neaby-qr-${c.code}.png`" class="icon-btn h-8 w-8" aria-label="Download PNG"><Download :size="15" /></a></Tip>
                        <Tip :label="c.active ? 'Switch off' : 'Switch on'"><button class="icon-btn h-8 w-8" :aria-label="c.active ? 'Switch off' : 'Switch on'" @click="toggle(c)"><component :is="c.active ? Pause : Play" :size="15" /></button></Tip>
                        <Tip label="Delete"><button class="icon-btn h-8 w-8 text-red-600" aria-label="Delete" @click="remove(c)"><Trash2 :size="15" /></button></Tip>
                    </div>
                </div>
            </article>
        </div>
        <EmptyState v-else title="No QR codes yet" :body="products.length ? 'Create one for a product and print it — scans go straight to the cart.' : 'Add a product first, then make a QR code for it.'">
            <template #icon><QrCode :size="28" /></template>
            <button v-if="products.length" class="btn-primary" @click="creating = true"><Plus :size="17" /> New code</button>
            <Link v-else :href="route('sell.products.create')" class="btn-primary">Add a product</Link>
        </EmptyState>

        <!-- Create -->
        <Modal :show="creating" max-width="lg" @close="creating = false">
            <form class="grid gap-4 p-5" @submit.prevent="submit">
                <h2 class="font-display text-base font-bold">New QR code</h2>
                <div>
                    <label class="label" for="qr-product">Product</label>
                    <select id="qr-product" v-model.number="form.product_id" class="input">
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.title }} — {{ p.price }}{{ p.status !== 'published' ? ` (${p.status})` : '' }}</option>
                    </select>
                    <p v-if="form.errors.product_id" class="mt-1 text-xs text-red-600">{{ form.errors.product_id }}</p>
                </div>
                <div>
                    <label class="label" for="qr-label">Label <span class="font-normal text-ink-400">(optional)</span></label>
                    <input id="qr-label" v-model="form.label" class="input" maxlength="60" placeholder="e.g. Lekki pop-up, Sat only" />
                </div>
                <div class="grid grid-cols-[1fr_1fr] gap-3">
                    <div>
                        <label class="label" for="qr-dtype">Discount</label>
                        <select id="qr-dtype" v-model="form.discount_type" class="input"><option value="">None</option><option value="percent">% off</option><option value="amount">₦ off</option></select>
                    </div>
                    <div>
                        <label class="label" for="qr-dval">{{ form.discount_type === 'amount' ? 'Amount (₦)' : 'Percent' }}</label>
                        <input id="qr-dval" v-model="form.discount_value" type="number" min="0" :max="form.discount_type === 'percent' ? limits.max_discount_percent : undefined" step="any" class="input" :disabled="!form.discount_type" />
                    </div>
                </div>
                <p v-if="form.errors.discount_value" class="-mt-2 text-xs text-red-600">{{ form.errors.discount_value }}</p>
                <p v-else-if="estimate" class="-mt-2 text-xs font-medium text-brand-700">Buyers pay {{ estimate }} each (was {{ product?.price }}).</p>

                <OptionPicker v-if="product?.options?.length" v-model="form.preset_options" :options="product.options" :error="form.errors.preset_options" />
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="qr-qty">Quantity</label>
                        <input id="qr-qty" v-model.number="form.default_quantity" type="number" min="1" max="1000" class="input" />
                    </div>
                    <div>
                        <label class="label" for="qr-max">Max uses <span class="font-normal text-ink-400">(optional)</span></label>
                        <input id="qr-max" v-model="form.max_uses" type="number" min="1" class="input" placeholder="Unlimited" />
                    </div>
                </div>
                <div>
                    <label class="label" for="qr-exp">Expires <span class="font-normal text-ink-400">(optional)</span></label>
                    <input id="qr-exp" v-model="form.expires_at" type="datetime-local" :min="minExpiry" class="input" />
                    <p v-if="form.errors.expires_at" class="mt-1 text-xs text-red-600">{{ form.errors.expires_at }}</p>
                </div>
                <label class="flex items-center gap-2 text-[13.5px]"><input v-model="form.allow_adjust" type="checkbox" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500" /> Let buyers change size and quantity</label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="creating = false">Cancel</button>
                    <button class="btn-primary" :disabled="form.processing">Create code</button>
                </div>
            </form>
        </Modal>

        <!-- Preview -->
        <Modal :show="!!preview" max-width="sm" @close="preview = null">
            <div v-if="preview" class="p-5 text-center">
                <img :src="preview.png" alt="QR code" class="mx-auto aspect-square w-full max-w-[16rem]" />
                <div class="mt-3 text-[14px] font-semibold">{{ preview.label || preview.product?.title }}</div>
                <div class="text-xs text-ink-500">{{ preview.url.replace(/^https?:\/\//, '') }}</div>
                <div class="mt-4 flex justify-center gap-2">
                    <a :href="preview.png" :download="`neaby-qr-${preview.code}.png`" class="btn-primary btn-sm"><Download :size="15" /> Download</a>
                    <a :href="preview.url" target="_blank" class="btn-ghost btn-sm">Open</a>
                </div>
            </div>
        </Modal>
    </AppShell>
</template>
