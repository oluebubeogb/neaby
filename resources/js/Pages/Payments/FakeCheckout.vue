<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { FlaskConical, ShieldCheck } from 'lucide-vue-next';
import SeoHead from '@/Components/SeoHead.vue';
import Wordmark from '@/Components/Wordmark.vue';

const props = defineProps({ payment: Object, seo: Object });
const busy = ref(false);
const go = (outcome) => { busy.value = true; router.post(route('payments.fake.complete', props.payment.reference), { outcome }, { onFinish: () => (busy.value = false) }); };
</script>

<template>
    <SeoHead :seo="seo" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-ink-50 p-4">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-amber-100 px-4 py-1.5 text-sm font-bold text-amber-900"><FlaskConical :size="16" /> TEST MODE — no real money moves</div>
        <div class="surface w-full max-w-sm p-6 text-center">
            <Wordmark size="md" class="mx-auto" />
            <p class="mt-4 text-sm text-ink-500">{{ payment.description }}</p>
            <div class="mt-2 font-display text-3xl font-extrabold">{{ payment.amount }}</div>
            <div class="mt-1 text-xs text-ink-400">Ref {{ payment.reference }}</div>
            <div v-if="payment.status === 'pending'" class="mt-6 grid gap-2">
                <button class="btn-primary btn-lg" :disabled="busy" @click="go('paid')"><ShieldCheck :size="18" /> Simulate successful payment</button>
                <button class="btn-ghost" :disabled="busy" @click="go('failed')">Simulate failed payment</button>
            </div>
            <p v-else class="mt-6 text-sm">This payment is already <b>{{ payment.status }}</b>.</p>
        </div>
        <p class="mt-4 max-w-sm text-center text-xs text-ink-400">Set PAYMENT_PROVIDER=monnify with your Monnify keys to take real payments.</p>
    </div>
</template>
