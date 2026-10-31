<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useAvailability } from '@/composables/availability';

const form = useForm({ name: '', username: '', email: '', password: '', password_confirmation: '' });

const u = useAvailability('username', () => form.username);
const em = useAvailability('email', () => form.email);

function onUsername(e) {
    form.username = e.target.value.toLowerCase().replace(/^@/, '').replace(/[^a-z0-9_]/g, '').slice(0, 24);
}
const submit = () => form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Create account" />
    <AuthLayout title="Create your account" subtitle="Free forever to browse. Open a store whenever you're ready.">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="label">Full name</label>
                <input id="name" v-model="form.name" type="text" class="input" required autofocus autocomplete="name" />
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <label for="username" class="label">Username</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-400">@</span>
                    <input id="username" :value="form.username" type="text" class="input pl-9" required autocomplete="username" autocapitalize="none" spellcheck="false" @input="onUsername" />
                </div>
                <InputError v-if="form.errors.username || u.state.value === 'taken'" :message="form.errors.username || u.message.value" />
                <p v-else-if="u.state.value === 'ok'" class="hint !text-brand-700">@{{ form.username }} is available</p>
                <p v-else class="hint">Lowercase letters, numbers and _ — this is your neaby handle.</p>
            </div>

            <div>
                <label for="email" class="label">Email</label>
                <input id="email" v-model="form.email" type="email" class="input" required autocomplete="email" />
                <InputError :message="form.errors.email || (em.state.value === 'taken' ? em.message.value : '')" />
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <PasswordInput id="password" v-model="form.password" required autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="label">Confirm password</label>
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" required autocomplete="new-password" />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <button class="btn-primary btn-lg w-full" :disabled="form.processing">Create account</button>
        </form>

        <template #footer>
            Already have an account? <Link :href="route('login')" class="font-semibold text-brand-700 hover:underline">Sign in</Link>
        </template>
    </AuthLayout>
</template>
