<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';

defineProps({ status: { type: String } });
const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Forgot password" />
    <AuthLayout title="Reset your password" subtitle="Enter your email and we'll send you a reset link.">
        <div v-if="status" class="mb-4 rounded-2xl bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">{{ status }}</div>
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" v-model="form.email" type="email" class="input" required autofocus autocomplete="email" />
                <InputError :message="form.errors.email" />
            </div>
            <button class="btn-primary btn-lg w-full" :disabled="form.processing">Email reset link</button>
        </form>
        <template #footer>
            Remembered it? <Link :href="route('login')" class="font-semibold text-brand-700 hover:underline">Back to sign in</Link>
        </template>
    </AuthLayout>
</template>
