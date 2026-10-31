<script setup>
/** Wireframe shopping cart (line icon, matches lucide stroke). */
defineProps({ size: { type: [Number, String], default: 20 }, strokeWidth: { type: [Number, String], default: 2 } });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="strokeWidth" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2.5 3.5h2.2l2.6 11.2a1.6 1.6 0 0 0 1.56 1.25h8.6a1.6 1.6 0 0 0 1.56-1.22L20.9 7.5H6" />
        <path d="M9.4 7.5l.8 8.4M13.2 7.5v8.4M17 7.5l-.9 8.4M6.9 11.7h13" />
        <circle cx="9.5" cy="19.6" r="1.4" />
        <circle cx="17.2" cy="19.6" r="1.4" />
    </svg>
</template>
