<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Check, ImageOff, Search, ShieldOff, X } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import Pager from '@/Components/Pager.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ products: Object, filters: Object, statuses: Array, seo: Object });
const q = ref(props.filters.q);
const search = () => router.get(route('admin.products.index'), { status: props.filters.status, q: q.value || undefined }, { preserveState: true });

const approve = (p) => router.post(route('admin.products.update', p.id), { action: 'approve' }, { preserveScroll: true });

const target = ref(null);
const form = useForm({ action: 'reject', reason: '' });
const openReject = (p, action = 'reject') => { target.value = p; form.action = action; form.reason = ''; form.clearErrors(); };
const submitReject = () => form.post(route('admin.products.update', target.value.id), { preserveScroll: true, onSuccess: () => (target.value = null) });
const presets = ['Photos are unclear or not of the actual item.', 'Prohibited item.', 'Price or description looks misleading.', 'Duplicate listing.'];

const tone = { published: 'bg-brand-100 text-brand-800', pending: 'bg-amber-100 text-amber-800', draft: 'bg-ink-100 text-ink-700', paused: 'bg-sky-100 text-sky-800', rejected: 'bg-red-100 text-red-700', archived: 'bg-ink-100 text-ink-500' };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Moderation" :back="route('admin.index')" wide>
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center">
            <div class="no-scrollbar -mx-3 flex gap-2 overflow-x-auto px-3 sm:mx-0 sm:px-0">
                <Link v-for="s in statuses" :key="s" :href="route('admin.products.index', { status: s, q: filters.q || undefined })" class="chip capitalize" :class="filters.status === s && 'chip-active'">{{ s }}</Link>
            </div>
            <form class="relative md:ml-auto md:w-72" @submit.prevent="search">
                <Search :size="17" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400" />
                <input v-model="q" type="search" placeholder="Search titles" class="input rounded-full py-2.5 pl-10" />
            </form>
        </div>

        <p class="mb-3 text-sm text-ink-500">{{ products.total }} listings</p>

        <div v-if="products.data.length" class="grid gap-3 xl:grid-cols-2">
            <article v-for="p in products.data" :key="p.id" class="surface flex gap-4 p-4">
                <a :href="route('products.show', p.slug)" target="_blank" class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-ink-100 md:h-28 md:w-28">
                    <img v-if="p.image" :src="p.image" alt="" class="h-full w-full object-cover" loading="lazy" />
                    <div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="20" /></div>
                </a>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start gap-2">
                        <a :href="route('products.show', p.slug)" target="_blank" class="line-clamp-1 flex-1 font-semibold hover:text-brand-700">{{ p.title }}</a>
                        <span class="badge shrink-0 capitalize" :class="tone[p.status]">{{ p.status }}</span>
                    </div>
                    <div class="mt-0.5 text-sm text-ink-500">
                        <span class="font-semibold text-ink-800">{{ p.price }}</span> ·
                        <a v-if="p.store" :href="route('stores.show', p.store.slug)" target="_blank" class="hover:underline">{{ p.store.name }}</a>
                        <span v-if="p.owner"> · @{{ p.owner }}</span>
                        <span v-if="p.category"> · {{ p.category.name }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-sm text-ink-600">{{ p.description }}</p>
                    <p v-if="p.moderation_note" class="mt-1 text-xs text-red-700">Note: {{ p.moderation_note }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-if="p.status !== 'published'" class="btn-primary btn-sm" @click="approve(p)"><Check :size="15" /> Approve</button>
                        <button v-if="p.status === 'pending'" class="btn-ghost btn-sm" @click="openReject(p)"><X :size="15" /> Request changes</button>
                        <button v-if="p.status === 'published'" class="btn-ghost btn-sm text-red-600" @click="openReject(p, 'unpublish')"><ShieldOff :size="15" /> Take down</button>
                        <span class="ml-auto self-center text-xs text-ink-400">{{ p.updated_at }}</span>
                    </div>
                </div>
            </article>
        </div>
        <EmptyState v-else title="Queue is clear" body="Nothing waiting for review. Nice." color="#16b26a">
            <template #icon><Check :size="28" /></template>
        </EmptyState>
        <Pager :prev="products.prev_page_url" :next="products.next_page_url" />

        <Modal :show="!!target" max-width="lg" @close="target = null">
            <form v-if="target" class="p-6" @submit.prevent="submitReject">
                <h2 class="font-display text-base font-bold">{{ form.action === 'reject' ? 'Request changes' : 'Take down listing' }}</h2>
                <p class="mt-1 line-clamp-1 text-sm text-ink-500">{{ target.title }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-for="r in presets" :key="r" type="button" class="chip text-xs" @click="form.reason = r">{{ r }}</button>
                </div>
                <textarea v-model="form.reason" rows="3" class="input mt-3" placeholder="Reason shown to the seller" />
                <InputError :message="form.errors.reason" />
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="target = null">Cancel</button>
                    <button class="btn-danger" :disabled="form.processing">Send</button>
                </div>
            </form>
        </Modal>
    </AppShell>
</template>
