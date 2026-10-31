<script setup>
// Decorative collage for the auth brand panel — pure CSS/SVG, no network images,
// so it always renders (offline, fresh deploys, empty storage).
import { MapPin, MessageCircle, BadgeCheck, Star, ShoppingBag, Shirt, Sparkles } from 'lucide-vue-next';
</script>

<template>
    <div class="pointer-events-none relative mx-auto my-8 h-[19rem] w-full max-w-[30rem] select-none xl:h-[21rem]" aria-hidden="true">
        <!-- soft glow behind the collage -->
        <div class="absolute left-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-400/25 blur-3xl" />

        <!-- product card (back, left) -->
        <div class="absolute left-0 top-10 w-40 -rotate-6 animate-float-slow rounded-3xl bg-white/95 p-2.5 text-ink-900 shadow-2xl shadow-black/40 xl:w-44">
            <div class="relative grid aspect-square place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-amber-200 via-orange-300 to-rose-400">
                <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-white/30" />
                <Sparkles :size="44" class="text-white drop-shadow" />
            </div>
            <p class="mt-2 truncate px-1 text-[13px] font-semibold">Shea body butter</p>
            <p class="px-1 pb-1 font-display text-sm font-extrabold text-brand-700">₦7,500</p>
        </div>

        <!-- store card (front, centre) -->
        <div class="absolute left-1/2 top-4 w-52 -translate-x-1/2 rotate-2 animate-float rounded-3xl bg-white p-2.5 text-ink-900 shadow-2xl shadow-black/50 xl:w-56">
            <div class="h-20 rounded-2xl bg-gradient-to-br from-brand-400 via-emerald-500 to-teal-600" />
            <div class="relative -mt-7 flex items-end gap-2 px-2">
                <span class="grid h-12 w-12 place-items-center rounded-full bg-gradient-to-br from-fuchsia-400 to-rose-500 font-display text-base font-bold text-white ring-4 ring-white">GC</span>
                <span class="mb-0.5 inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700"><BadgeCheck :size="11" /> Verified</span>
            </div>
            <div class="px-2 pb-2 pt-2">
                <p class="font-display text-[15px] font-extrabold leading-tight">Glow by Chi</p>
                <p class="mt-0.5 flex items-center gap-1 text-[11px] text-ink-500"><MapPin :size="11" /> Enugu · 1.2 km away</p>
                <div class="mt-2.5 grid grid-cols-3 gap-1.5">
                    <div class="grid aspect-square place-items-center rounded-xl bg-gradient-to-br from-sky-200 to-indigo-300"><Shirt :size="16" class="text-white" /></div>
                    <div class="grid aspect-square place-items-center rounded-xl bg-gradient-to-br from-lime-200 to-emerald-400"><ShoppingBag :size="16" class="text-white" /></div>
                    <div class="grid aspect-square place-items-center rounded-xl bg-gradient-to-br from-amber-200 to-orange-400"><Sparkles :size="16" class="text-white" /></div>
                </div>
            </div>
        </div>

        <!-- chat bubble (right) -->
        <div class="absolute -right-2 top-48 w-48 animate-float-alt rounded-2xl rounded-br-md bg-white/95 p-3 text-ink-900 shadow-xl shadow-black/40 xl:top-52">
            <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-brand-700"><MessageCircle :size="12" /> New message</p>
            <p class="mt-1 text-[12.5px] leading-snug">Is it still available? I'm close by 👋</p>
        </div>

        <!-- rating chip (bottom left) -->
        <div class="absolute bottom-2 left-10 flex items-center gap-1.5 rounded-full bg-ink-900/80 px-3 py-1.5 text-[11px] font-semibold text-white ring-1 ring-white/15 backdrop-blur">
            <Star :size="12" class="fill-amber-300 text-amber-300" /> 4.9 · 120 happy buyers
        </div>
    </div>
</template>
