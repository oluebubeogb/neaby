<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import IconCircle from '@/Components/IconCircle.vue';

const props = defineProps({ action: String, email: String, seo: Object });
const form = useForm({});
const submit = () => form.post(props.action);
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Delete account" :back="route('profile.edit')">
        <div class="surface mx-auto max-w-md p-6 text-center">
            <IconCircle color="#e11d48" :size="56" class="mx-auto"><TriangleAlert :size="24" /></IconCircle>
            <h1 class="mt-4 font-display text-lg font-bold">Delete {{ email }}?</h1>
            <p class="mt-2 text-sm text-ink-500">Your profile, messages and saved items are removed and your store goes offline. This can’t be undone.</p>
            <div class="mt-6 flex justify-center gap-2">
                <Link :href="route('profile.edit')" class="btn-ghost">Keep my account</Link>
                <button class="btn-danger" :disabled="form.processing" @click="submit">Delete permanently</button>
            </div>
        </div>
    </AppShell>
</template>
