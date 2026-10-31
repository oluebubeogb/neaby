<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps({ prev: { type: String, default: null }, next: { type: String, default: null }, label: { type: String, default: '' } });
</script>

<template>
    <nav v-if="prev || next" class="mt-8 flex items-center justify-center gap-3" aria-label="Pagination">
        <Link v-if="prev" :href="prev" preserve-scroll class="btn-ghost"><ChevronLeft :size="16" /> Previous</Link>
        <span v-if="label" class="text-sm text-ink-500">{{ label }}</span>
        <Link v-if="next" :href="next" class="btn-ghost">Next <ChevronRight :size="16" /></Link>
    </nav>
</template>
