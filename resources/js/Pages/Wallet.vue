<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowDownLeft, ArrowUpRight, Lock, Plus, Wallet as WalletIcon, X } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';

const props = defineProps({ wallet: Object, history: Object, pendingHolds: Number, limits: Object, provider: String, seo: Object });

const funding = ref(false);
const presets = [2000, 5000, 10000, 25000, 50000];
const form = useForm({ amount: '' });
const naira = (n) => '₦' + Number(n).toLocaleString('en-NG');
function fund() {
    form.post(route('wallet.fund'));
}
const purposeLabel = { topup: 'Wallet top-up', order: 'Order payment', refund: 'Refund', adjustment: 'Adjustment' };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Wallet">
        <div class="mx-auto grid max-w-3xl gap-5">
            <!-- Balance -->
            <section class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand-700 via-brand-800 to-ink-900 p-6 text-white shadow-lift">
                <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl" />
                <div class="flex items-center gap-2 text-sm font-semibold text-white/75"><WalletIcon :size="17" /> Available balance</div>
                <div class="mt-2 font-display text-4xl font-extrabold tracking-tight">{{ wallet.available }}</div>
                <p v-if="wallet.held" class="mt-1 text-sm text-white/70">{{ wallet.held }} on hold for a checkout in progress</p>
                <button class="btn mt-5 rounded-full bg-white px-5 font-bold text-brand-800 hover:bg-brand-50" @click="funding = !funding">
                    <component :is="funding ? X : Plus" :size="17" /> {{ funding ? 'Close' : 'Fund wallet' }}
                </button>
            </section>

            <!-- Fund (starts here in neaby; paid through the gateway) -->
            <section v-if="funding" class="surface p-5">
                <form class="grid gap-4" @submit.prevent="fund">
                    <div>
                        <label class="label" for="amount">Amount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-semibold text-ink-500">₦</span>
                            <input id="amount" v-model="form.amount" type="number" inputmode="numeric" :min="limits.min" :max="limits.max" step="1" required class="input pl-9 text-lg font-semibold" placeholder="5,000" />
                        </div>
                        <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="p in presets" :key="p" type="button" class="chip" :class="Number(form.amount) === p && 'chip-active'" @click="form.amount = p">{{ naira(p) }}</button>
                    </div>
                    <button class="btn-primary btn-lg w-full" :disabled="form.processing || !form.amount"><Lock :size="16" /> Fund {{ form.amount ? naira(form.amount) : '' }}</button>
                    <p class="hint text-center">{{ provider === 'fake' ? 'Test mode — no real money moves.' : 'You’ll pay securely via Monnify — card, bank transfer or USSD.' }} {{ naira(limits.min) }}–{{ naira(limits.max) }} per top-up.</p>
                </form>
            </section>

            <!-- History -->
            <section>
                <h2 class="section-title mb-3">History</h2>
                <div v-if="history.data.length" class="surface divide-y divide-ink-100 overflow-hidden">
                    <div v-for="t in history.data" :key="t.id" class="flex items-center gap-3 p-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="t.type === 'credit' ? 'bg-brand-50 text-brand-700' : 'bg-ink-100 text-ink-700'">
                            <component :is="t.type === 'credit' ? ArrowDownLeft : ArrowUpRight" :size="18" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ t.description || purposeLabel[t.purpose] || t.purpose }}</div>
                            <div class="text-xs text-ink-500">{{ t.at }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-display font-bold" :class="t.type === 'credit' ? 'text-brand-700' : 'text-ink-900'">{{ t.type === 'credit' ? '+' : '−' }}{{ t.amount }}</div>
                            <div class="text-xs text-ink-400">Bal. {{ t.balance_after }}</div>
                        </div>
                    </div>
                </div>
                <EmptyState v-else title="No wallet activity yet" body="Fund your wallet to check out in one tap.">
                    <template #icon><WalletIcon :size="28" /></template>
                </EmptyState>
                <Pager :prev="history.prev_page_url" :next="history.next_page_url" />
            </section>
        </div>
    </AppShell>
</template>
