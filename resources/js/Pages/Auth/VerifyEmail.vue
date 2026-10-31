<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({ status: { type: String } });
const form = useForm({});
const submit = () => form.post(route('verification.send'));
const sent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Verify email" />
    <AuthLayout title="Check your inbox" subtitle="We sent a verification link to your email. Didn't get it? We can send another.">
        <div v-if="sent" class="mb-4 rounded-2xl bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">A new verification link is on its way.</div>
        <form class="flex items-center justify-between gap-3" @submit.prevent="submit">
            <button class="btn-primary" :disabled="form.processing">Resend email</button>
            <Link :href="route('logout')" method="post" as="button" class="text-sm font-semibold text-ink-500 hover:text-ink-800">Log out</Link>
        </form>
    </AuthLayout>
</template>
