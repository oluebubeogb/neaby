<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Lock, Store, Truck, Wallet } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ cart: Object, defaults: Object, states: Array, provider: String, wallet: Object, seo: Object });
const form = useForm({ delivery_method: 'delivery', contact_name: props.defaults.contact_name || '', contact_phone: props.defaults.contact_phone || '', address: '', city: props.defaults.city || '', state: props.defaults.state || '', note: '', use_wallet: (props.wallet?.available_minor || 0) > 0 });
const total = computed(() => (form.delivery_method === 'pickup' ? props.cart.total_pickup : props.cart.total_delivery));
const totalMinor = computed(() => (form.delivery_method === 'pickup' ? props.cart.total_pickup_minor : props.cart.total_delivery_minor));

// Pay with wallet (full), on the go (Monnify), or split: wallet first, the rest on the go.
const money = (minor) => '₦' + (minor / 100).toLocaleString('en-NG', { maximumFractionDigits: minor % 100 ? 2 : 0 });
const walletPart = computed(() => (form.use_wallet ? Math.min(props.wallet?.available_minor || 0, totalMinor.value) : 0));
const remainder = computed(() => totalMinor.value - walletPart.value);
const gateway = computed(() => (props.provider === 'fake' ? 'test checkout' : 'Monnify'));
const payLabel = computed(() => {
    if (walletPart.value > 0 && remainder.value === 0) return `Pay ${money(walletPart.value)} from wallet`;
    if (walletPart.value > 0) return `Pay ${money(remainder.value)} + ${money(walletPart.value)} wallet`;
    return `Pay ${total.value}`;
});
const submit = () => form.transform((d) => ({ ...d, use_wallet: d.use_wallet ? 1 : 0 })).post(route('checkout.place'));
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Checkout" :back="route('cart.index')">
        <form class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_340px]" @submit.prevent="submit">
            <div class="grid gap-4">
                <section class="surface p-5">
                    <h2 class="section-title mb-3">How do you want it?</h2>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-2xl border p-3" :class="form.delivery_method === 'delivery' ? 'border-brand-500 bg-brand-50' : 'border-ink-200'"><input v-model="form.delivery_method" type="radio" value="delivery" class="sr-only" /><Truck :size="18" /> Delivery</label>
                        <label class="flex items-center gap-2 rounded-2xl border p-3" :class="[form.delivery_method === 'pickup' ? 'border-brand-500 bg-brand-50' : 'border-ink-200', cart.pickup_ok ? 'cursor-pointer' : 'cursor-not-allowed opacity-50']"><input v-model="form.delivery_method" type="radio" value="pickup" class="sr-only" :disabled="!cart.pickup_ok" /><Store :size="18" /> Pickup</label>
                    </div>
                    <p v-if="!cart.pickup_ok" class="hint mt-2">Pickup isn't offered for every item in your cart.</p>
                </section>
                <section class="surface grid gap-3 p-5">
                    <h2 class="section-title">Contact</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label">Full name</label><input v-model="form.contact_name" class="input" autocomplete="name" /><p v-if="form.errors.contact_name" class="mt-1 text-xs text-red-600">{{ form.errors.contact_name }}</p></div>
                        <div><label class="label">Phone</label><input v-model="form.contact_phone" class="input" type="tel" autocomplete="tel" /><p v-if="form.errors.contact_phone" class="mt-1 text-xs text-red-600">{{ form.errors.contact_phone }}</p></div>
                    </div>
                    <template v-if="form.delivery_method === 'delivery'">
                        <div><label class="label">Delivery address</label><input v-model="form.address" class="input" autocomplete="street-address" /><p v-if="form.errors.address" class="mt-1 text-xs text-red-600">{{ form.errors.address }}</p></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="label">City / area</label><input v-model="form.city" class="input" /><p v-if="form.errors.city" class="mt-1 text-xs text-red-600">{{ form.errors.city }}</p></div>
                            <div><label class="label">State</label><select v-model="form.state" class="input"><option value="">Choose…</option><option v-for="s in states" :key="s" :value="s">{{ s }}</option></select><p v-if="form.errors.state" class="mt-1 text-xs text-red-600">{{ form.errors.state }}</p></div>
                        </div>
                    </template>
                    <div><label class="label">Note for the seller (optional)</label><textarea v-model="form.note" rows="2" class="input" /></div>
                </section>
            </div>
            <aside class="surface h-fit p-5 lg:sticky lg:top-24">
                <div v-for="g in cart.groups" :key="g.store.slug" class="mb-3 text-sm">
                    <div class="font-semibold">{{ g.store.name }}</div>
                    <div v-for="i in g.items" :key="i.id" class="flex justify-between text-ink-600"><span class="truncate pr-2">{{ i.quantity }} × {{ i.product.title }}</span><span>{{ i.line_total }}</span></div>
                </div>
                <div class="flex justify-between border-t border-ink-100 pt-3 text-sm"><span>Subtotal</span><span>{{ cart.subtotal }}</span></div>
                <div class="flex justify-between text-sm text-ink-500"><span>Delivery</span><span>{{ form.delivery_method === 'pickup' ? '₦0' : cart.delivery }}</span></div>
                <div class="mt-3 flex justify-between border-t border-ink-100 pt-3 font-display text-lg font-extrabold"><span>Total</span><span>{{ total }}</span></div>

                <!-- Wallet -->
                <div v-if="wallet" class="mt-4 rounded-2xl border p-3" :class="form.use_wallet && walletPart ? 'border-brand-300 bg-brand-50/60' : 'border-ink-200'">
                    <label class="flex items-center gap-3" :class="wallet.available_minor > 0 ? 'cursor-pointer' : 'opacity-60'">
                        <input v-model="form.use_wallet" type="checkbox" class="rounded border-ink-300 text-brand-600 focus:ring-brand-200" :disabled="wallet.available_minor <= 0" />
                        <Wallet :size="18" class="text-brand-700" />
                        <span class="flex-1 text-sm font-semibold">Use wallet</span>
                        <span class="text-sm font-bold tabular-nums">{{ wallet.available }}</span>
                    </label>
                    <div v-if="walletPart > 0" class="mt-2 space-y-0.5 border-t border-brand-100 pt-2 text-sm">
                        <div class="flex justify-between text-ink-600"><span>From wallet</span><span>−{{ money(walletPart) }}</span></div>
                        <div class="flex justify-between font-semibold"><span>{{ remainder ? 'Pay on the go' : 'Left to pay' }}</span><span>{{ money(remainder) }}</span></div>
                    </div>
                    <Link v-else-if="wallet.available_minor <= 0" :href="route('wallet.index')" class="mt-1 block text-xs font-semibold text-brand-700 hover:underline">Fund wallet</Link>
                </div>

                <p v-for="(e, k) in form.errors" v-show="!['contact_name','contact_phone','address','city','state'].includes(k)" :key="k" class="mt-2 text-xs text-red-600">{{ e }}</p>
                <button class="btn-primary btn-lg mt-4 w-full" :disabled="form.processing"><Lock :size="16" /> {{ payLabel }}</button>
                <p class="hint mt-2">
                    <template v-if="remainder === 0 && walletPart > 0">Paid instantly from your neaby wallet.</template>
                    <template v-else-if="provider === 'fake'">Test mode — no real money moves.</template>
                    <template v-else>You’ll pay {{ walletPart ? 'the rest ' : '' }}securely via {{ gateway }} — card, bank transfer or USSD.<template v-if="walletPart"> Your wallet is only charged once that payment goes through.</template></template>
                </p>
            </aside>
        </form>
    </AppShell>
</template>
