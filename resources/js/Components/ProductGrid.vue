<script setup>
import ProductCard from '@/Components/ProductCard.vue';

defineProps({
    products: { type: Array, required: true },
    showStore: { type: Boolean, default: true },
    dense: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4"
        :class="dense ? '2xl:grid-cols-6' : '2xl:grid-cols-5'"
    >
        <ProductCard
            v-for="(p, i) in products"
            :key="p.id"
            :product="p"
            :show-store="showStore"
            class="animate-rise"
            :style="{ animationDelay: Math.min(i % 24, 12) * 25 + 'ms' }"
        />
    </div>
</template>
