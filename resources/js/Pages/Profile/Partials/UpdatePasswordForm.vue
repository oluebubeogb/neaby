<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import PasswordInput from '@/Components/PasswordInput.vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const form = useForm({ current_password: '', password: '', password_confirmation: '' });

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus?.();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus?.();
            }
        },
    });
};
</script>

<template>
    <section>
        <h2 class="font-display text-lg font-bold">Password</h2>
        <p class="mt-1 text-sm text-ink-500">Use a long, unique password to keep your store safe.</p>

        <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
            <div>
                <label class="label" for="current_password">Current password</label>
                <PasswordInput id="current_password" ref="currentPasswordInput" v-model="form.current_password" autocomplete="current-password" />
                <InputError :message="form.errors.current_password" />
            </div>
            <div>
                <label class="label" for="password">New password</label>
                <PasswordInput id="password" ref="passwordInput" v-model="form.password" autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" />
                <InputError :message="form.errors.password_confirmation" />
            </div>
            <div class="flex items-center gap-4">
                <button class="btn-dark" :disabled="form.processing">Update password</button>
                <p v-if="form.recentlySuccessful" class="text-sm font-medium text-brand-700">Saved.</p>
            </div>
        </form>
    </section>
</template>
