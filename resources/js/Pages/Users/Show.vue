<script setup>
import { MapPin } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import StoreChip from '@/Components/StoreChip.vue';

defineProps({ profile: Object, store: Object, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="'@' + profile.username">
        <div class="surface mx-auto max-w-2xl p-6 text-center md:p-10">
            <Avatar :src="profile.avatar" :initials="profile.initials" :size="96" class="mx-auto shadow-lift" />
            <h1 class="mt-4 font-display text-2xl font-extrabold">{{ profile.name }}</h1>
            <p class="text-ink-500">@{{ profile.username }}</p>
            <p v-if="profile.bio" class="mx-auto mt-3 max-w-md text-ink-700">{{ profile.bio }}</p>
            <p class="mt-3 flex items-center justify-center gap-3 text-sm text-ink-500">
                <span v-if="profile.city || profile.state" class="inline-flex items-center gap-1"><MapPin :size="14" /> {{ [profile.city, profile.state].filter(Boolean).join(', ') }}</span>
                <span>Joined {{ profile.joined }}</span>
            </p>
            <div v-if="store" class="mt-8 text-left">
                <h2 class="section-title mb-3">Store</h2>
                <StoreChip :store="store" />
            </div>
        </div>
    </AppShell>
</template>
