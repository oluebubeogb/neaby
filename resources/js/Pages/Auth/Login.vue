<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PasswordInput from '@/Components/PasswordInput.vue';

defineProps({ canResetPassword: { type: Boolean }, status: { type: String } });

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Sign in" />
    <AuthLayout title="Welcome back" subtitle="Sign in to manage your store and listings.">
        <div v-if="status" class="mb-4 rounded-2xl bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">{{ status }}</div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="label">Email or username</label>
                <input id="email" v-model="form.email" type="text" class="input" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="text-sm font-semibold text-ink-700">Password</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-semibold text-brand-700 hover:underline">Forgot password?</Link>
                </div>
                <PasswordInput id="password" v-model="form.password" required autocomplete="current-password" />
                <InputError :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-2.5 text-sm text-ink-600">
                <Checkbox v-model:checked="form.remember" name="remember" /> Keep me signed in
            </label>

            <button class="btn-primary btn-lg w-full" :disabled="form.processing">Sign in</button>
        </form>

        <template #footer>
            New to neaby? <Link :href="route('register')" class="font-semibold text-brand-700 hover:underline">Create a free account</Link>
        </template>
    </AuthLayout>
</template>
