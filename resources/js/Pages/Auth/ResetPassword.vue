<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import PasswordInput from '@/Components/PasswordInput.vue';

const props = defineProps({ email: { type: String, required: true }, token: { type: String, required: true } });
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Choose a new password" />
    <AuthLayout title="Choose a new password">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" v-model="form.email" type="email" class="input" required autocomplete="username" />
                <InputError :message="form.errors.email" />
            </div>
            <div>
                <label for="password" class="label">New password</label>
                <PasswordInput id="password" v-model="form.password" required autofocus autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm password</label>
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" required autocomplete="new-password" />
                <InputError :message="form.errors.password_confirmation" />
            </div>
            <button class="btn-primary btn-lg w-full" :disabled="form.processing">Reset password</button>
        </form>
    </AuthLayout>
</template>
