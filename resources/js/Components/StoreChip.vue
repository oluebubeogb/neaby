<script setup>
import { Link } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import { MapPin } from 'lucide-vue-next';

defineProps({ store: { type: Object, required: true } });
</script>

<template>
    <Link
        :href="route('stores.show', store.slug)"
        class="group flex min-w-[15rem] items-center gap-3 rounded-3xl border border-ink-200/70 bg-white p-3 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lift"
    >
        <Avatar :src="store.logo" :initials="store.initials" :accent="store.accent" :size="48" rounded="rounded-2xl" />
        <div class="min-w-0">
            <div class="truncate font-display font-bold text-ink-900">{{ store.name }}</div>
            <div class="flex items-center gap-1 truncate text-xs text-ink-500">
                <template v-if="store.city"><MapPin :size="11" /> {{ store.city }}<span class="mx-1 text-ink-300">·</span></template>
                <span v-if="store.products_count !== null">{{ store.products_count }} items</span>
                <span v-else class="truncate">{{ store.tagline }}</span>
            </div>
        </div>
    </Link>
</template>
