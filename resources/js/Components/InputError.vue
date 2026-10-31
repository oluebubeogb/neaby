<script setup>
defineProps({ message: { type: String } });
</script>

<template>
    <p v-show="message" class="mt-1.5 text-sm font-medium text-red-600" role="alert">{{ message }}</p>
</template>
