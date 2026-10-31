<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';

const props = defineProps({ productId: { type: Number, required: true }, saved: Boolean, label: Boolean, size: { type: Number, default: 19 } });
const on = ref(props.saved);
watch(() => props.saved, (v) => (on.value = v));
const page = usePage();

function toggle() {
    if (!page.props.auth?.user) return router.visit(route('login'));
    on.value = !on.value;
    router.post(route('saved.toggle', props.productId), {}, { preserveScroll: true, preserveState: true, onError: () => (on.value = !on.value) });
}
</script>

<template>
    <button type="button" :class="label ? 'btn-ghost btn-lg' : 'icon-btn'" :aria-pressed="on" :aria-label="on ? 'Remove from saved' : 'Save'" @click.prevent.stop="toggle">
        <Heart :size="size" :class="on ? 'fill-red-500 text-red-500' : ''" />
        <span v-if="label">{{ on ? 'Saved' : 'Save' }}</span>
    </button>
</template>
