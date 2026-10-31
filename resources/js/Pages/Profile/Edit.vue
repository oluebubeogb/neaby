<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineProps({ mustVerifyEmail: Boolean, status: String, states: Array, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Settings" :back="route('profile.show')">
        <div class="mx-auto max-w-2xl space-y-6">
            <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" :states="states" class="surface p-5 md:p-7" />
            <UpdatePasswordForm class="surface p-5 md:p-7" />
            <DeleteUserForm class="surface p-5 md:p-7" />
        </div>
    </AppShell>
</template>
