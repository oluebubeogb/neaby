<script setup>
defineProps({ status: String, label: String });
const tone = {
    pending_payment: 'bg-amber-100 text-amber-800', paid: 'bg-sky-100 text-sky-800', processing: 'bg-sky-100 text-sky-800',
    shipped: 'bg-violet-100 text-violet-800', ready_for_pickup: 'bg-violet-100 text-violet-800', delivered: 'bg-brand-100 text-brand-800',
    completed: 'bg-brand-100 text-brand-800', cancelled: 'bg-ink-100 text-ink-600', payment_failed: 'bg-red-100 text-red-700', refunded: 'bg-ink-100 text-ink-600',
};
</script>

<template>
    <span class="badge" :class="tone[status] || 'bg-ink-100 text-ink-700'">{{ label || status }}</span>
</template>
