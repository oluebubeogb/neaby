<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight, LayoutDashboard, LogOut, Plus, Settings, ShieldCheck, Store, UserRound, Receipt, Heart, BadgeCheck, Repeat2, Inbox } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import IconCircle from '@/Components/IconCircle.vue';

defineProps({ store: Object, seo: Object });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Profile">
        <div class="mx-auto max-w-xl space-y-4">
            <section class="surface relative overflow-hidden p-6 text-center">
                <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-br from-brand-400/30 via-emerald-200/30 to-sky-200/30" />
                <div class="relative">
                    <Avatar :src="$page.props.auth.user.avatar" :initials="$page.props.auth.user.initials" :size="88" class="mx-auto shadow-lift ring-4 ring-white" />
                    <h1 class="mt-3 font-display text-xl font-extrabold">{{ $page.props.auth.user.name }}</h1>
                    <p class="text-sm text-ink-500">@{{ $page.props.auth.user.username }}</p>
                    <p v-if="$page.props.auth.user.bio" class="mx-auto mt-2 max-w-sm text-sm text-ink-700">{{ $page.props.auth.user.bio }}</p>
                    <div class="mt-4 flex justify-center gap-2">
                        <Link :href="route('profile.edit')" class="btn-ghost btn-sm"><Settings :size="15" /> Edit profile</Link>
                        <Link :href="route('users.show', $page.props.auth.user.username)" class="btn-ghost btn-sm"><UserRound :size="15" /> Public view</Link>
                    </div>
                </div>
            </section>

            <section class="surface divide-y divide-ink-100 overflow-hidden">
                <Link v-if="store" :href="route('stores.show', store.slug)" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <Avatar :src="store.logo" :initials="store.initials" :accent="store.accent" :size="44" rounded="rounded-2xl" />
                    <div class="min-w-0 flex-1"><div class="font-semibold">{{ store.name }}</div><div class="text-sm text-ink-500">{{ store.products_count }} live items</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('sell.dashboard')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#16b26a" :size="44"><LayoutDashboard v-if="store" :size="20" /><Store v-else :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">{{ store ? 'Seller hub' : 'Open a store' }}</div><div class="text-sm text-ink-500">{{ store ? 'Listings, stats and status' : 'Start selling in a minute' }}</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link v-if="store" :href="route('sell.products.create')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#d97706" :size="44"><Plus :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">New listing</div><div class="text-sm text-ink-500">Photos + description, we'll do the title</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('orders.index')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#16b26a" :size="44"><Receipt :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">My orders</div><div class="text-sm text-ink-500">Track purchases and reservations</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('saved.index')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#e11d48" :size="44"><Heart :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">Saved</div><div class="text-sm text-ink-500">Items you've saved</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('membership.index')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#7c3aed" :size="44"><BadgeCheck :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">Membership</div><div class="text-sm text-ink-500">Reseller and supplier plans</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('resell.index')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#7c3aed" :size="44"><Repeat2 :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">Resell</div><div class="text-sm text-ink-500">Supplier products and your offers</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('requests.index')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#0891b2" :size="44"><Inbox :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">Supplier requests</div><div class="text-sm text-ink-500">Stock checks and holds</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('profile.edit')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#0891b2" :size="44"><Settings :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">Account settings</div><div class="text-sm text-ink-500">Profile, password, delete account</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link v-if="$page.props.auth.user.is_admin" :href="route('admin.index')" class="flex items-center gap-3 p-4 hover:bg-ink-50">
                    <IconCircle color="#4f46e5" :size="44"><ShieldCheck :size="20" /></IconCircle>
                    <div class="flex-1"><div class="font-semibold">Admin</div><div class="text-sm text-ink-500">Moderation and categories</div></div>
                    <ChevronRight :size="18" class="text-ink-300" />
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 p-4 text-left hover:bg-red-50">
                    <IconCircle color="#e11d48" :size="44"><LogOut :size="20" /></IconCircle>
                    <div class="flex-1 font-semibold text-red-600">Sign out</div>
                </Link>
            </section>
        </div>
    </AppShell>
</template>
