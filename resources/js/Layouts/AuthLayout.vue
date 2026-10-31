<script setup>
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, Sparkles, Store } from 'lucide-vue-next';
import Wordmark from '@/Components/Wordmark.vue';
import IconCircle from '@/Components/IconCircle.vue';
import Flash from '@/Components/Flash.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="relative grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
        <div class="ambient" aria-hidden="true" />

        <!-- Brand panel: tablet+ gets a hero band, desktop a full side panel -->
        <section class="relative hidden overflow-hidden bg-ink-950 p-12 text-white lg:flex lg:flex-col">
            <div class="absolute -right-32 -top-32 h-[34rem] w-[34rem] animate-drift rounded-full bg-brand-500/35 blur-3xl" />
            <div class="absolute -bottom-40 -left-24 h-[30rem] w-[30rem] animate-drift-alt rounded-full bg-teal-400/20 blur-3xl" />
            <Link :href="route('landing')" class="relative"><Wordmark tone="white" size="lg" /></Link>
            <div class="relative mt-auto max-w-md">
                <h2 class="font-display text-4xl font-extrabold leading-[1.08] tracking-tight">Your store, your people, <span class="text-brand-300">nearby.</span></h2>
                <p class="mt-4 text-white/70">Open a free storefront, list in minutes and share it everywhere your customers already are.</p>
                <ul class="mt-10 space-y-4 text-sm text-white/85">
                    <li class="flex items-center gap-3"><IconCircle color="#38cc83" :size="38"><Store :size="18" /></IconCircle> A beautiful store link in 60 seconds</li>
                    <li class="flex items-center gap-3"><IconCircle color="#fbbf24" :size="38"><Sparkles :size="18" /></IconCircle> AI writes your listing titles</li>
                    <li class="flex items-center gap-3"><IconCircle color="#5eead4" :size="38"><ShieldCheck :size="18" /></IconCircle> Reviewed listings, real local sellers</li>
                </ul>
            </div>
        </section>

        <section class="flex flex-col px-5 pb-10 pt-safe sm:px-8">
            <div class="flex h-16 items-center lg:hidden">
                <Link :href="route('landing')"><Wordmark size="md" /></Link>
            </div>
            <div class="m-auto w-full max-w-[26rem] py-8">
                <div class="surface p-6 sm:p-8">
                    <h1 class="font-display text-2xl font-extrabold text-ink-900 sm:text-[1.7rem]">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-1.5 text-sm text-ink-500">{{ subtitle }}</p>
                    <div class="mt-6"><slot /></div>
                </div>
                <div class="mt-6 text-center text-sm text-ink-500"><slot name="footer" /></div>
            </div>
        </section>
        <Flash />
    </div>
</template>
