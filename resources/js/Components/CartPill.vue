<script setup>
/**
 * Cart shortcut with a live count badge. Bumps and floats "+n" whenever the
 * cart count goes up, and is a [data-cart-target] for flyToCart().
 */
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import CartIcon from '@/Components/icons/CartIcon.vue';

const props = defineProps({ dark: Boolean, label: { type: Boolean, default: false } });
const page = usePage();
const count = computed(() => Number(page.props.counts?.cart || 0));
const plus = ref(0);
const bumpKey = ref(0);
let t = null;
watch(count, (n, o) => {
    if (n > o) {
        plus.value = n - o;
        bumpKey.value++;
        clearTimeout(t);
        t = setTimeout(() => (plus.value = 0), 1400);
    }
});
</script>

<template>
    <Link
        :href="route('cart.index')"
        data-cart-target
        class="relative inline-flex items-center gap-2 rounded-full transition active:scale-95"
        :class="[dark ? 'immersive-btn h-10 w-auto min-w-10 px-2.5' : 'h-10 border border-ink-200 bg-white px-3 text-ink-800 shadow-sm hover:border-brand-300 hover:text-brand-700', count ? '' : 'opacity-90']"
        :aria-label="`Cart, ${count} item${count === 1 ? '' : 's'}`"
    >
        <CartIcon :size="19" />
        <span v-if="label" class="text-[13px] font-semibold">{{ count ? 'View cart' : 'Cart' }}</span>
        <span
            v-if="count"
            :key="bumpKey"
            class="cart-badge absolute -right-1.5 -top-1.5 min-w-[20px] rounded-full bg-brand-500 px-1.5 text-center text-[11px] font-extrabold leading-5 text-white shadow-[0_4px_12px_-2px_rgba(22,178,106,.7)] ring-2"
            :class="dark ? 'ring-ink-950/60' : 'ring-white'"
        >{{ count > 99 ? '99+' : count }}</span>
        <Transition enter-from-class="opacity-0 translate-y-1" leave-to-class="opacity-0 -translate-y-3" enter-active-class="transition duration-200" leave-active-class="transition duration-500">
            <span v-if="plus" class="pointer-events-none absolute -top-7 right-0 rounded-full bg-brand-500 px-2 text-[11px] font-extrabold leading-5 text-white shadow">+{{ plus }}</span>
        </Transition>
    </Link>
</template>

<style scoped>
.cart-badge { animation: cart-pop .5s cubic-bezier(.3, 1.6, .5, 1); }
@keyframes cart-pop { 0% { transform: scale(.3); } 60% { transform: scale(1.35); } 100% { transform: scale(1); } }
@media (prefers-reduced-motion: reduce) { .cart-badge { animation: none; } }
</style>
