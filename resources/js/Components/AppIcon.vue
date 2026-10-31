<script setup>
import { computed } from 'vue';
import {
    Baby, BookOpen, Car, Dumbbell, HeartPulse, Laptop, Palette, Scissors, Shirt,
    ShoppingBag, ShoppingBasket, Smartphone, Sofa, Sparkles, Tag, Watch, Wrench,
} from 'lucide-vue-next';

// Category icons are stored as lucide names (kebab-case) in the DB.
const map = {
    baby: Baby, 'book-open': BookOpen, car: Car, dumbbell: Dumbbell, 'heart-pulse': HeartPulse,
    laptop: Laptop, palette: Palette, scissors: Scissors, shirt: Shirt, 'shopping-bag': ShoppingBag,
    'shopping-basket': ShoppingBasket, smartphone: Smartphone, sofa: Sofa, sparkles: Sparkles,
    tag: Tag, watch: Watch, wrench: Wrench,
};

const props = defineProps({ name: { type: String, default: 'tag' }, size: { type: Number, default: 18 } });
const icon = computed(() => map[props.name] || Tag);
</script>

<template>
    <component :is="icon" :size="size" :stroke-width="2" aria-hidden="true" />
</template>
