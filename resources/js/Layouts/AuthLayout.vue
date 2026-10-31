<script setup>
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, Sparkles, Store } from 'lucide-vue-next';
import Wordmark from '@/Components/Wordmark.vue';
import IconCircle from '@/Components/IconCircle.vue';
import AuthShowcase from '@/Components/AuthShowcase.vue';
import Flash from '@/Components/Flash.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="relative grid min-h-screen overflow-hidden lg:grid-cols-[1.05fr_1fr]">
        <div class="ambient" aria-hidden="true" />

        <!-- Brand backdrop: dark green panel that dissolves into the white form side like smoke
             (masked fade + drifting white plumes) instead of a hard vertical edge. -->
        <div class="auth-smoke pointer-events-none absolute inset-y-0 left-0 hidden w-[66%] lg:block" aria-hidden="true">
            <div class="auth-smoke__panel absolute inset-0 bg-ink-950">
                <div class="absolute -right-10 -top-32 h-[34rem] w-[34rem] animate-drift rounded-full bg-brand-500/35 blur-3xl" />
                <div class="absolute -bottom-40 -left-24 h-[30rem] w-[30rem] animate-drift-alt rounded-full bg-teal-400/20 blur-3xl" />
                <div class="absolute bottom-10 right-24 h-80 w-80 animate-drift rounded-full bg-emerald-400/15 blur-3xl" />
                <!-- tint the fade zone green so it dissolves green → white, never grey -->
                <div class="absolute inset-0 bg-[linear-gradient(to_right,transparent_40%,rgba(22,178,106,.32)_72%,rgba(56,204,131,.5)_100%)]" />
            </div>
            <div class="auth-smoke__plume auth-smoke__plume--1" />
            <div class="auth-smoke__plume auth-smoke__plume--2" />
            <div class="auth-smoke__plume auth-smoke__plume--3" />
        </div>

        <section class="relative z-10 hidden flex-col p-12 pr-20 text-white lg:flex">
            <Link :href="route('landing')" class="relative self-start"><Wordmark tone="white" size="lg" /></Link>
            <div class="flex flex-1 items-center">
                <AuthShowcase />
            </div>
            <div class="relative max-w-md">
                <h2 class="font-display text-3xl font-extrabold leading-[1.08] tracking-tight">Your store, your people, <span class="text-brand-300">nearby.</span></h2>
                <p class="mt-4 text-white/70">Open a free storefront, list in minutes and share it everywhere your customers already are.</p>
                <ul class="mt-8 space-y-4 text-sm text-white/85">
                    <li class="flex items-center gap-3"><IconCircle color="#38cc83" :size="38"><Store :size="18" /></IconCircle> A beautiful store link in 60 seconds</li>
                    <li class="flex items-center gap-3"><IconCircle color="#fbbf24" :size="38"><Sparkles :size="18" /></IconCircle> AI writes your listing titles</li>
                    <li class="flex items-center gap-3"><IconCircle color="#5eead4" :size="38"><ShieldCheck :size="18" /></IconCircle> Reviewed listings, real local sellers</li>
                </ul>
            </div>
        </section>

        <section class="relative z-10 flex flex-col px-5 pb-10 pt-safe sm:px-8">
            <div class="flex h-16 items-center lg:hidden">
                <Link :href="route('landing')"><Wordmark size="md" /></Link>
            </div>
            <div class="m-auto w-full max-w-[26rem] py-8">
                <div class="surface p-6 sm:p-8">
                    <h1 class="font-display text-xl font-extrabold text-ink-900 sm:text-[1.375rem]">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-1.5 text-sm text-ink-500">{{ subtitle }}</p>
                    <div class="mt-6"><slot /></div>
                </div>
                <div class="mt-6 text-center text-sm text-ink-500"><slot name="footer" /></div>
            </div>
        </section>
        <Flash />
    </div>
</template>

<style scoped>
/* Panel fades out over its last ~30% so the green melts into the page. */
.auth-smoke__panel {
    -webkit-mask-image: linear-gradient(to right, #000 0%, #000 64%, rgba(0, 0, 0, .78) 74%, rgba(0, 0, 0, .35) 86%, transparent 100%);
    mask-image: linear-gradient(to right, #000 0%, #000 64%, rgba(0, 0, 0, .78) 74%, rgba(0, 0, 0, .35) 86%, transparent 100%);
}
/* Soft white plumes drifting over the seam — the "smoke". */
.auth-smoke__plume {
    position: absolute;
    border-radius: 9999px;
    background: radial-gradient(closest-side, rgba(255, 255, 255, .95), rgba(255, 255, 255, .55) 45%, rgba(255, 255, 255, 0));
    filter: blur(28px);
}
.auth-smoke__plume--1 { width: 22rem; height: 30rem; right: -6rem; top: -4rem; animation: smoke 26s ease-in-out infinite; }
.auth-smoke__plume--2 { width: 18rem; height: 24rem; right: 2rem; top: 38%; opacity: .7; animation: smoke 31s ease-in-out -9s infinite reverse; }
.auth-smoke__plume--3 { width: 26rem; height: 22rem; right: -8rem; bottom: -6rem; opacity: .85; animation: smoke 34s ease-in-out -17s infinite; }
@keyframes smoke {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    33% { transform: translate3d(-2.5rem, 1.5rem, 0) scale(1.08); }
    66% { transform: translate3d(1rem, -2rem, 0) scale(.95); }
}
@media (prefers-reduced-motion: reduce) {
    .auth-smoke__plume { animation: none; }
}
</style>
