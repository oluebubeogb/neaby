<script setup>
/**
 * Immersive, breakpoint-specific shell:
 *  - mobile  (<768):   glass top bar + bottom tab bar with raised Sell button
 *  - tablet  (768–1023): 76px icon rail with tooltips
 *  - desktop (≥1024):  248px sidebar, collapsible to 76px (persisted)
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, ChevronsLeft, ChevronsRight, LogIn, LogOut, MessageCircle, Plus, Search, Settings, Store } from 'lucide-vue-next';
import CartIcon from '@/Components/icons/CartIcon.vue';
import CallOverlay from '@/Components/CallOverlay.vue';
import Wordmark from '@/Components/Wordmark.vue';
import Monogram from '@/Components/Monogram.vue';
import Avatar from '@/Components/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Flash from '@/Components/Flash.vue';
import { primaryNav, secondaryNav, isActive } from '@/composables/nav';
import { useSidebar } from '@/composables/useSidebar';

const props = defineProps({
    title: { type: String, default: '' },
    back: { type: String, default: null },
    wide: { type: Boolean, default: false },
    hideMobileHeader: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const counts = computed(() => page.props.counts || {});
const nav = computed(() => primaryNav(user.value, counts.value));
const extra = computed(() => secondaryNav(user.value, counts.value, page.props.features || {}, page.props.entitlements || []));
const categories = computed(() => page.props.categories || []);
const { collapsed, toggle } = useSidebar();

const q = ref('');
function submitSearch() {
    router.get(route('search'), q.value ? { q: q.value } : {});
}
</script>

<template>
    <div class="min-h-screen">
        <div class="ambient" aria-hidden="true" />

        <!-- ───────── Tablet rail (md) ───────── -->
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-[var(--nav-rail)] flex-col items-center border-r border-ink-200/70 bg-white/80 py-4 backdrop-blur-xl md:flex lg:hidden">
            <Link :href="route('home')" class="mb-6" aria-label="neaby home"><Monogram :size="42" /></Link>
            <nav class="flex flex-1 flex-col items-center gap-1.5">
                <Link
                    v-for="item in [...nav, ...extra]"
                    :key="item.key"
                    :href="item.href"
                    class="group relative flex h-12 w-12 items-center justify-center rounded-2xl transition"
                    :class="[
                        item.primary ? 'my-1 bg-brand-500 text-white shadow-glow hover:bg-brand-600' :
                        isActive(item) ? 'bg-brand-50 text-brand-700' : 'text-ink-500 hover:bg-ink-100 hover:text-ink-900',
                    ]"
                    :aria-label="item.label"
                >
                    <component :is="item.primary ? Plus : item.icon" :size="22" :stroke-width="isActive(item) || item.primary ? 2.4 : 2" />
                    <span v-if="item.badge" class="absolute right-1 top-1 min-w-[18px] rounded-full bg-red-500 px-1 text-center text-[10px] font-bold leading-[18px] text-white">{{ item.badge > 99 ? '99+' : item.badge }}</span>
                    <span class="pointer-events-none absolute left-[calc(100%+12px)] whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1 text-xs font-semibold text-white opacity-0 shadow-lift transition group-hover:opacity-100 group-focus-visible:opacity-100">
                        {{ item.label }}
                    </span>
                </Link>
            </nav>
            <Link v-if="user" :href="route('profile.show')" aria-label="Your profile">
                <Avatar :src="user.avatar" :initials="user.initials" :size="40" />
            </Link>
        </aside>

        <!-- ───────── Desktop sidebar (lg) ───────── -->
        <aside
            class="fixed inset-y-0 left-0 z-40 hidden flex-col border-r border-ink-200/70 bg-white/80 backdrop-blur-xl transition-[width] duration-300 ease-[cubic-bezier(.2,.8,.2,1)] lg:flex"
            :class="collapsed ? 'w-[var(--nav-rail)]' : 'w-[var(--nav-sidebar)]'"
        >
            <div class="flex shrink-0 items-center px-4" :class="collapsed ? 'h-auto flex-col gap-2 pb-2 pt-3' : 'h-16 justify-between'">
                <Link :href="route('home')" aria-label="neaby home" class="flex items-center">
                    <Monogram v-if="collapsed" :size="38" />
                    <Wordmark v-else size="md" class="pl-1" />
                </Link>
                <button v-if="!collapsed" class="icon-btn h-8 w-8" aria-label="Collapse sidebar" title="Collapse sidebar" @click="toggle"><ChevronsLeft :size="18" /></button>
                <button
                    v-else
                    class="group relative flex h-8 w-11 items-center justify-center rounded-xl border border-ink-200 bg-white text-ink-600 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                    aria-label="Expand sidebar"
                    @click="toggle"
                >
                    <ChevronsRight :size="18" :stroke-width="2.4" />
                    <span class="pointer-events-none absolute left-[calc(100%+12px)] whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1 text-xs font-semibold text-white opacity-0 shadow-lift transition group-hover:opacity-100">Expand</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto overflow-x-hidden px-3 pb-4 no-scrollbar">
                <form v-if="!collapsed" class="relative mb-3 mt-1" role="search" @submit.prevent="submitSearch">
                    <Search :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400" />
                    <input v-model="q" type="search" placeholder="Search neaby" class="w-full rounded-2xl border-0 bg-ink-100/80 py-2.5 pl-10 pr-3 text-sm placeholder:text-ink-400 focus:bg-white focus:ring-2 focus:ring-brand-200" />
                </form>

                <nav class="flex flex-col gap-0.5">
                    <template v-for="item in [...nav, ...extra]" :key="item.key">
                        <Link
                            :href="item.href"
                            class="group relative flex h-11 items-center gap-3 rounded-2xl text-[14.5px] font-semibold transition"
                            :class="[
                                collapsed ? 'justify-center px-0' : 'px-3',
                                item.primary
                                    ? 'my-1.5 bg-brand-500 text-white shadow-glow hover:bg-brand-600'
                                    : isActive(item) ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900',
                            ]"
                            :title="collapsed ? item.label : undefined"
                        >
                            <component :is="item.primary ? Plus : item.icon" :size="20" :stroke-width="isActive(item) || item.primary ? 2.4 : 2" class="shrink-0" />
                            <span v-if="!collapsed" class="truncate">{{ item.primary ? 'Sell something' : item.label }}</span>
                            <span v-if="item.badge && !collapsed" class="ml-auto min-w-[20px] rounded-full bg-red-500 px-1.5 text-center text-[11px] font-bold leading-5 text-white">{{ item.badge > 99 ? '99+' : item.badge }}</span>
                            <span v-else-if="item.badge && collapsed" class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500" />
                            <span v-else-if="isActive(item) && !item.primary && !collapsed" class="ml-auto h-1.5 w-1.5 rounded-full bg-brand-500" />
                        </Link>
                    </template>
                </nav>

                <div v-if="!collapsed && categories.length" class="mt-6">
                    <div class="mb-2 px-3 text-[11px] font-bold uppercase tracking-[.12em] text-ink-400">Browse</div>
                    <Link
                        v-for="c in categories.filter((x) => x && x.slug).slice(0, 10)"
                        :key="c.id"
                        :href="route('categories.show', c.slug)"
                        class="flex h-9 items-center gap-3 rounded-xl px-3 text-sm text-ink-600 transition hover:bg-ink-100 hover:text-ink-900"
                        :class="route().current('categories.show', { category: c.slug }) ? 'bg-ink-100 font-semibold text-ink-900' : ''"
                    >
                        <span class="flex h-6 w-6 items-center justify-center rounded-full" :style="{ background: c.accent + '1a', color: c.accent }"><AppIcon :name="c.icon" :size="13" /></span>
                        <span class="truncate">{{ c.name }}</span>
                    </Link>
                    <Link :href="route('categories.index')" class="mt-1 block px-3 py-1.5 text-sm font-semibold text-brand-700 hover:underline">All categories →</Link>
                </div>
            </div>

            <div class="shrink-0 border-t border-ink-200/70 p-3">
                <template v-if="user">
                    <div class="flex items-center gap-3 rounded-2xl p-1.5" :class="collapsed ? 'justify-center' : ''">
                        <Link :href="route('profile.show')"><Avatar :src="user.avatar" :initials="user.initials" :size="36" /></Link>
                        <div v-if="!collapsed" class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-ink-900">{{ user.name }}</div>
                            <div class="truncate text-xs text-ink-500">@{{ user.username }}</div>
                        </div>
                        <Link v-if="!collapsed" :href="route('profile.edit')" class="icon-btn h-8 w-8" aria-label="Settings"><Settings :size="16" /></Link>
                    </div>
                    <div v-if="!collapsed" class="mt-1 flex gap-1">
                        <Link v-if="user.store_slug" :href="route('stores.show', user.store_slug)" class="btn-sm flex-1 btn-ghost"><Store :size="14" /> My store</Link>
                        <Link :href="route('logout')" method="post" as="button" class="btn-sm btn-ghost"><LogOut :size="14" /> Log out</Link>
                    </div>
                </template>
                <template v-else>
                    <div v-if="!collapsed" class="grid gap-2">
                        <Link :href="route('register')" class="btn-primary">Create free account</Link>
                        <Link :href="route('login')" class="btn-ghost">Sign in</Link>
                    </div>
                    <Link v-else :href="route('login')" class="icon-btn mx-auto flex" aria-label="Sign in"><LogIn :size="18" /></Link>
                </template>
            </div>
        </aside>

        <!-- ───────── Main column ───────── -->
        <div
            class="transition-[padding] duration-300 ease-[cubic-bezier(.2,.8,.2,1)] md:pl-[var(--nav-rail)]"
            :class="collapsed ? 'lg:pl-[var(--nav-rail)]' : 'lg:pl-[var(--nav-sidebar)]'"
        >
            <!-- Mobile top bar -->
            <header v-if="!hideMobileHeader" class="glass pt-safe sticky top-0 z-30 border-b md:hidden">
                <div class="flex h-14 items-center gap-2 px-3">
                    <Link v-if="back" :href="back" class="icon-btn -ml-1" aria-label="Back">
                        <ArrowLeft :size="20" />
                    </Link>
                    <Link v-if="!title" :href="route('home')" class="pl-1"><Wordmark size="sm" /></Link>
                    <h1 v-else class="truncate font-display text-[17px] font-bold">{{ title }}</h1>
                    <div class="ml-auto flex items-center gap-1">
                        <slot name="mobile-actions" />
                        <Link v-if="user" :href="route('messages.index')" class="icon-btn relative" aria-label="Messages"><MessageCircle :size="20" /><span v-if="counts.messages" class="absolute right-0.5 top-0.5 min-w-[16px] rounded-full bg-red-500 px-1 text-center text-[9.5px] font-bold leading-4 text-white">{{ counts.messages > 9 ? '9+' : counts.messages }}</span></Link>
                        <Link v-if="user && counts.cart" :href="route('cart.index')" data-cart-target class="icon-btn relative" aria-label="Cart"><CartIcon :size="20" /><span class="absolute right-0.5 top-0.5 min-w-[16px] rounded-full bg-brand-600 px-1 text-center text-[9.5px] font-bold leading-4 text-white">{{ counts.cart }}</span></Link>
                        <Link v-if="!route().current('search')" :href="route('search')" class="icon-btn" aria-label="Search"><Search :size="20" /></Link>
                        <Link v-if="user" :href="route('profile.show')" aria-label="Profile"><Avatar :src="user.avatar" :initials="user.initials" :size="32" /></Link>
                        <Link v-else :href="route('login')" class="btn-sm btn-dark">Sign in</Link>
                    </div>
                </div>
            </header>

            <!-- Tablet / desktop page header -->
            <header v-if="title || $slots.actions" class="sticky top-0 z-30 hidden border-b border-ink-200/50 bg-ink-50/80 backdrop-blur-xl md:block">
                <div class="mx-auto flex h-16 items-center gap-3 px-6 lg:px-8" :class="wide ? 'max-w-[1600px]' : 'max-w-7xl'">
                    <Link v-if="back" :href="back" class="icon-btn -ml-2" aria-label="Back"><ArrowLeft :size="20" /></Link>
                    <h1 class="truncate font-display text-lg font-bold text-ink-900">{{ title }}</h1>
                    <div class="ml-auto flex items-center gap-2"><slot name="actions" /></div>
                </div>
            </header>

            <main
                class="mx-auto px-3 pb-[calc(6.5rem+var(--safe-bottom))] pt-4 sm:px-4 md:px-6 md:pb-12 md:pt-6 lg:px-8"
                :class="wide ? 'max-w-[1600px]' : 'max-w-7xl'"
            >
                <slot />
            </main>
        </div>

        <!-- ───────── Mobile bottom nav ───────── -->
        <nav class="glass pb-safe fixed inset-x-0 bottom-0 z-40 border-t md:hidden" aria-label="Primary">
            <div class="mx-auto grid h-16 max-w-lg grid-cols-5 items-center px-2">
                <Link
                    v-for="item in nav"
                    :key="item.key"
                    :href="item.href"
                    class="flex flex-col items-center justify-center gap-0.5 text-[10.5px] font-semibold"
                    :class="isActive(item) ? 'text-brand-700' : 'text-ink-500'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <template v-if="item.primary">
                        <span class="-mt-7 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-glow ring-4 ring-white transition active:scale-95">
                            <Plus :size="26" :stroke-width="2.6" />
                        </span>
                        <span class="mt-0.5" :class="isActive(item) ? 'text-brand-700' : 'text-ink-600'">Sell</span>
                    </template>
                    <template v-else>
                        <span class="relative flex h-8 w-14 items-center justify-center rounded-full transition" :class="isActive(item) ? 'bg-brand-50' : ''">
                            <component :is="item.icon" :size="21" :stroke-width="isActive(item) ? 2.5 : 2" />
                            <span v-if="item.badge" class="absolute right-2 top-0 min-w-[16px] rounded-full bg-red-500 px-1 text-center text-[9.5px] font-bold leading-4 text-white">{{ item.badge > 9 ? '9+' : item.badge }}</span>
                        </span>
                        {{ item.label }}
                    </template>
                </Link>
            </div>
        </nav>

        <Flash />
        <CallOverlay v-if="user" />
    </div>
</template>
