<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import PasswordInput from '@/Components/PasswordInput.vue';

const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm'), { onFinish: () => form.reset() });
</script>

<template>
    <Head title="Confirm password" />
    <AuthLayout title="Confirm your password" subtitle="This is a secure area — please confirm your password to continue.">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="password" class="label">Password</label>
                <PasswordInput id="password" v-model="form.password" required autofocus autocomplete="current-password" />
                <InputError :message="form.errors.password" />
            </div>
            <button class="btn-primary btn-lg w-full" :disabled="form.processing">Confirm</button>
        </form>
    </AuthLayout>
</template>
