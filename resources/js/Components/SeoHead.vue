<script setup>
import { Head } from '@inertiajs/vue3';

// Full meta is server-rendered in app.blade.php; client navigations keep the title in sync.
defineProps({ seo: { type: Object, default: null }, title: { type: String, default: '' } });
</script>

<template>
    <Head :title="title || seo?.name || 'neaby'" />
</template>
