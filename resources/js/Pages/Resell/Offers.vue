<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ExternalLink, ImageOff, Pencil, Repeat2, Share2, Trash2 } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ShareSheet from '@/Components/ShareSheet.vue';

defineProps({ offers: Array, seo: Object });
const sharing = ref(null);
const remove = (o) => confirm('Delete this offer?') && router.delete(route('resell.offers.destroy', o.id), { preserveScroll: true });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="My offers" :back="route('resell.index')" wide>
        <div v-if="offers.length" class="grid gap-3 md:grid-cols-2">
            <div v-for="o in offers" :key="o.id" class="surface flex gap-3 p-3">
                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-ink-100"><img v-if="o.image" :src="o.image" alt="" class="h-full w-full object-cover" /><div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="18" /></div></div>
                <div class="min-w-0 flex-1 text-sm">
                    <div class="truncate font-medium">{{ o.title }}</div>
                    <div class="flex flex-wrap items-center gap-2"><b>{{ o.price }}</b><span v-if="o.margin" class="text-xs" :class="o.margin.negative ? 'text-red-600' : 'text-brand-700'">margin {{ o.margin.gross }}</span>
                        <span class="badge" :class="o.live ? 'bg-brand-100 text-brand-800' : 'bg-ink-100 text-ink-600'">{{ o.live ? 'Live' : (o.inactive_reason || o.status) }}</span></div>
                    <div class="text-xs text-ink-500">{{ o.views }} views · {{ o.shares }} shares · {{ o.enquiries }} enquiries</div>
                    <div class="mt-2 flex gap-1">
                        <a :href="o.url" target="_blank" class="icon-btn h-8 w-8" aria-label="Open"><ExternalLink :size="14" /></a>
                        <button class="icon-btn h-8 w-8" aria-label="Share" @click="sharing = o"><Share2 :size="14" /></button>
                        <Link :href="route('resell.offers.edit', o.id)" class="icon-btn h-8 w-8" aria-label="Edit"><Pencil :size="14" /></Link>
                        <button class="icon-btn h-8 w-8 text-red-600" aria-label="Delete" @click="remove(o)"><Trash2 :size="14" /></button>
                    </div>
                </div>
            </div>
        </div>
        <EmptyState v-else title="No offers yet" body="Pick a supplier product and set your own price." color="#7c3aed"><template #icon><Repeat2 :size="28" /></template><Link :href="route('resell.index')" class="btn-primary">Browse supplier products</Link></EmptyState>
        <ShareSheet v-if="sharing" :show="!!sharing" kind="offer" :id="sharing.id" :url="sharing.url" :title="sharing.title" :text="`${sharing.title} — ${sharing.price}`" :card-base="route('share.offer', sharing.slug)" @close="sharing = null" />
    </AppShell>
</template>
