<script setup>
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { TriangleAlert } from 'lucide-vue-next';
import IconCircle from '@/Components/IconCircle.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PasswordInput from '@/Components/PasswordInput.vue';

const confirming = ref(false);
const passwordInput = ref(null);
const form = useForm({ password: '' });

const open = () => {
    confirming.value = true;
    nextTick(() => passwordInput.value?.focus?.());
};
const deleteUser = () =>
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => close(),
        onError: () => passwordInput.value?.focus?.(),
        onFinish: () => form.reset(),
    });
const close = () => {
    confirming.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section>
        <h2 class="font-display text-lg font-bold">Delete account</h2>
        <p class="mt-1 text-sm text-ink-500">This permanently removes your profile. Your store and listings go offline.</p>
        <button class="btn-danger mt-5" @click="open">Delete account</button>

        <Modal :show="confirming" @close="close">
            <div class="p-6">
                <IconCircle color="#e11d48" :size="48"><TriangleAlert :size="22" /></IconCircle>
                <h2 class="mt-4 font-display text-lg font-bold">Delete your account?</h2>
                <p class="mt-1 text-sm text-ink-500">Enter your password to confirm. This can't be undone.</p>
                <div class="mt-5">
                    <label for="delete_password" class="sr-only">Password</label>
                    <PasswordInput id="delete_password" ref="passwordInput" v-model="form.password" placeholder="Password" @keyup.enter="deleteUser" />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="btn-ghost" @click="close">Cancel</button>
                    <button class="btn-danger" :disabled="form.processing" @click="deleteUser">Delete account</button>
                </div>
            </div>
        </Modal>
    </section>
</template>
