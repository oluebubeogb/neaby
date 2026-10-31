<script setup>
/**
 * Shared hero for /s/{store} and /u/{user}. The avatar overlaps the cover (stacked above
 * it via z-index); name + handle and description + location sit in two columns
 * on wide screens (centre divider) and stack on mobile.
 */
import Avatar from '@/Components/Avatar.vue';

defineProps({
    cover: { type: String, default: null },
    accent: { type: String, default: '#16b26a' },
    avatar: { type: String, default: null },
    initials: { type: String, default: '?' },
    square: Boolean, // store logos are rounded squares, people are circles
    name: { type: String, required: true },
    handle: { type: String, default: null },
    tagline: { type: String, default: null },
});
</script>

<template>
    <section class="-mx-3 -mt-4 sm:mx-0 sm:mt-0">
        <div class="relative h-36 overflow-hidden sm:h-48 sm:rounded-[1.75rem] md:h-56" :style="{ background: `linear-gradient(135deg, ${accent}, ${accent}99 55%, #0a4b31)` }">
            <img v-if="cover" :src="cover" alt="" class="h-full w-full object-cover" fetchpriority="high" />
            <template v-else>
                <div class="absolute -right-10 -top-16 h-64 w-64 animate-drift rounded-full bg-white/20 blur-3xl" />
                <div class="absolute -bottom-20 left-10 h-48 w-48 rounded-full bg-black/10 blur-3xl" />
            </template>
            <div class="absolute inset-0 bg-gradient-to-t from-black/25 via-transparent to-transparent" />
        </div>

        <div class="relative z-10 px-4 sm:px-6">
            <!-- Avatar row: avatar sits ON TOP of the cover (z-index), actions beside it. -->
            <div class="flex items-end justify-between gap-3">
                <div class="relative z-20 -mt-11 shrink-0 sm:-mt-12">
                    <Avatar :src="avatar" :initials="initials" :accent="accent" :size="92" :rounded="square ? 'rounded-[1.6rem]' : 'rounded-full'" class="shadow-lift ring-4 ring-white" />
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 pt-3">
                    <slot name="actions" />
                </div>
            </div>

            <!-- Info row: two columns with a centre divider on wide screens, stacked on mobile. -->
            <div class="mt-3 grid min-w-0 gap-3 md:mt-4 md:grid-cols-2 md:gap-0 md:divide-x md:divide-ink-100">
                <div class="min-w-0 md:pr-6">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <h1 class="font-display text-xl font-extrabold leading-tight tracking-tight text-ink-900 md:text-[1.4rem]">{{ name }}</h1>
                        <slot name="badge" />
                    </div>
                    <div v-if="handle" class="mt-0.5 text-[13px] font-medium text-ink-500">{{ handle }}</div>
                </div>
                <div class="min-w-0 md:pl-6">
                    <p v-if="tagline" class="max-w-2xl text-[14px] font-medium leading-relaxed text-ink-800">{{ tagline }}</p>
                    <slot name="description" />
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-ink-500">
                        <slot name="meta" />
                    </div>
                </div>
            </div>
            <slot />
        </div>
    </section>
</template>
