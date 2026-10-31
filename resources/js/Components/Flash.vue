<script setup>
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, AlertCircle, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage();
const toast = ref(null);
let timer = null;

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        const msg = error || success;
        if (!msg) return;
        toast.value = { msg, error: Boolean(error), id: Date.now() };
        clearTimeout(timer);
        timer = setTimeout(() => (toast.value = null), 4200);
    },
    { immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-3 opacity-0"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="translate-y-3 opacity-0"
    >
        <div
            v-if="toast"
            :key="toast.id"
            class="fixed inset-x-3 bottom-[calc(5.5rem+var(--safe-bottom))] z-[60] mx-auto flex max-w-md items-center gap-3 rounded-2xl bg-ink-900 px-4 py-3 text-sm font-medium text-white shadow-lift md:bottom-6"
            role="status"
        >
            <AlertCircle v-if="toast.error" :size="18" class="shrink-0 text-red-400" />
            <CheckCircle2 v-else :size="18" class="shrink-0 text-brand-400" />
            <span class="flex-1">{{ toast.msg }}</span>
            <button class="rounded-lg p-1 text-white/60 hover:text-white" aria-label="Dismiss" @click="toast = null"><X :size="16" /></button>
        </div>
    </Transition>
</template>
