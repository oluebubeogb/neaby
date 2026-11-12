<script setup>
/**
 * Gift mode: occasion + who it's for + budget (+ an optional note). One plan
 * (RunPod when on, simple rules otherwise) → real in-stock listings near you,
 * each one tap from the cart.
 */
import { computed, reactive, ref } from 'vue';
import { Gift, Loader2, Sparkles, Wallet } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import ProductGrid from '@/Components/ProductGrid.vue';
import EmptyState from '@/Components/EmptyState.vue';
import NearMe from '@/Components/NearMe.vue';
import TripPicker from '@/Components/TripPicker.vue';
import { postJson } from '@/composables/http';

const props = defineProps({ occasions: Array, recipients: Array, wallet: Object, seo: Object });
const form = reactive({ occasion: 'birthday', recipient: 'her', budget: '', note: '' });
const busy = ref(false);
const result = ref(null); // { items, ideas, note }
const error = ref(null);
const labels = { her: 'Her', him: 'Him', mum: 'Mum', dad: 'Dad', friend: 'A friend', kid: 'A kid', colleague: 'A colleague', couple: 'A couple' };
const budgets = [5000, 10000, 20000, 50000];
const canUseBalance = computed(() => props.wallet && props.wallet.available_minor > 0);

async function suggest() {
    busy.value = true;
    error.value = null;
    try {
        result.value = await postJson(route('gift.suggest'), { ...form, budget: form.budget || null });
    } catch (e) {
        error.value = e.status === 429 ? 'Give it a minute and try again.' : e.message || 'That didn’t go through. Try again.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Gift ideas" :back="route('search')" wide>
        <div class="lg:grid lg:grid-cols-[340px_1fr] lg:gap-8">
            <form class="surface mb-6 p-5 lg:sticky lg:top-24 lg:mb-0 lg:self-start" @submit.prevent="suggest">
                <h1 class="flex items-center gap-2 font-display text-lg font-extrabold text-ink-900"><Gift :size="20" class="text-rose-500" /> Find a gift</h1>
                <p class="mt-1 text-sm text-ink-500">Real items in stock at stores near you.</p>

                <label class="label mt-4">Occasion</label>
                <div class="flex flex-wrap gap-2">
                    <button v-for="o in occasions" :key="o" type="button" class="chip capitalize" :class="form.occasion === o && 'chip-active'" @click="form.occasion = o">{{ o }}</button>
                </div>

                <label class="label mt-4">Who is it for?</label>
                <div class="flex flex-wrap gap-2">
                    <button v-for="r in recipients" :key="r" type="button" class="chip" :class="form.recipient === r && 'chip-active'" @click="form.recipient = r">{{ labels[r] || r }}</button>
                </div>

                <label class="label mt-4" for="gift-budget">Budget (₦)</label>
                <input id="gift-budget" v-model="form.budget" type="number" min="0" inputmode="numeric" class="input" placeholder="Any" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-for="b in budgets" :key="b" type="button" class="chip py-1.5 text-xs" :class="Number(form.budget) === b && 'chip-active'" @click="form.budget = b">Up to ₦{{ b.toLocaleString() }}</button>
                    <button v-if="canUseBalance" type="button" class="chip py-1.5 text-xs" :class="Number(form.budget) === Math.floor(wallet.available_minor / 100) && 'chip-active'" @click="form.budget = Math.floor(wallet.available_minor / 100)"><Wallet :size="13" /> My balance · {{ wallet.available }}</button>
                </div>

                <label class="label mt-4" for="gift-note">Anything else? <span class="font-normal text-ink-400">(optional)</span></label>
                <input id="gift-note" v-model="form.note" type="text" maxlength="200" class="input" placeholder="She loves gold jewellery and perfume" />

                <div class="mt-4 flex flex-wrap items-center gap-2"><NearMe /><TripPicker /></div>
                <button class="btn-primary mt-5 w-full" :disabled="busy"><Loader2 v-if="busy" :size="17" class="animate-spin" /><Sparkles v-else :size="17" /> Show gift ideas</button>
                <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
            </form>

            <section class="min-w-0">
                <template v-if="result">
                    <div class="mb-4">
                        <h2 class="section-title">Gift ideas</h2>
                        <p v-if="result.note" class="mt-1 text-sm text-ink-600">{{ result.note }}</p>
                        <div v-if="result.ideas?.length" class="no-scrollbar mt-2 flex gap-1.5 overflow-x-auto">
                            <span v-for="i in result.ideas" :key="i" class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold capitalize text-rose-700 ring-1 ring-rose-100">{{ i }}</span>
                        </div>
                    </div>
                    <ProductGrid v-if="result.items.length" :products="result.items" dense />
                    <EmptyState v-else title="No gifts in stock for that yet" body="Try a bigger budget or another idea." color="#e11d48">
                        <template #icon><Gift :size="28" /></template>
                    </EmptyState>
                </template>
                <EmptyState v-else title="Tell us about the gift" body="Pick the occasion, who it’s for and a budget — we’ll find real items nearby you can add to cart in one tap." color="#e11d48">
                    <template #icon><Gift :size="28" /></template>
                </EmptyState>
            </section>
        </div>
    </AppShell>
</template>
