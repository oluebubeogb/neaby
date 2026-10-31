<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Bell, CheckCheck, Package, Repeat2, Crown, MessageCircle } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import IconCircle from '@/Components/IconCircle.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ notifications: Array, unread: Number, seo: Object });
const icons = { order: [Package, '#16b26a'], resale: [Repeat2, '#7c3aed'], request: [Repeat2, '#7c3aed'], membership: [Crown, '#d97706'], general: [Bell, '#0ea5e9'] };
const iconFor = (k) => icons[k] || icons.general;
function open(n) {
    if (!n.read) router.post(route('inbox.read'), { id: n.id }, { preserveScroll: true, preserveState: true });
    if (n.url) router.visit(n.url);
}
const readAll = () => router.post(route('inbox.read'), {}, { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Inbox">
        <template #actions><button v-if="unread" class="btn-ghost btn-sm" @click="readAll"><CheckCheck :size="15" /> Mark all read</button></template>
        <div class="mx-auto max-w-2xl">
            <div class="mb-3 flex items-center justify-between md:hidden">
                <span class="text-sm text-ink-500">{{ unread }} unread</span>
                <button v-if="unread" class="btn-ghost btn-sm" @click="readAll"><CheckCheck :size="15" /> Mark all read</button>
            </div>
            <div v-if="notifications.length" class="surface divide-y divide-ink-100 overflow-hidden">
                <button v-for="n in notifications" :key="n.id" class="flex w-full items-start gap-3 p-4 text-left transition hover:bg-ink-50" :class="n.read ? '' : 'bg-brand-50/40'" @click="open(n)">
                    <IconCircle :color="iconFor(n.kind)[1]" :size="40"><component :is="iconFor(n.kind)[0]" :size="18" /></IconCircle>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2"><span class="font-semibold text-ink-900">{{ n.title }}</span><span v-if="!n.read" class="h-2 w-2 rounded-full bg-brand-500" /></div>
                        <p class="mt-0.5 text-sm text-ink-600">{{ n.body }}</p>
                        <div class="mt-1 text-xs text-ink-400">{{ n.ago }}</div>
                    </div>
                </button>
            </div>
            <EmptyState v-else title="You're all caught up" body="Order updates, supplier requests and membership alerts land here. To chat with a seller, tap “Chat seller” on any listing — it opens WhatsApp.">
                <template #icon><MessageCircle :size="28" /></template>
                <Link :href="route('home')" class="btn-primary">Keep browsing</Link>
            </EmptyState>
        </div>
    </AppShell>
</template>
