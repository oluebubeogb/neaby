<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { Download } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ batch: Object, rows: Array, seo: Object });
let t = null;
onMounted(() => { if (['queued', 'processing'].includes(props.batch.status)) t = setInterval(() => router.reload({ only: ['batch', 'rows'], onSuccess: () => { if (!['queued', 'processing'].includes(props.batch.status)) clearInterval(t); } }), 2500); });
onBeforeUnmount(() => clearInterval(t));
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="batch.filename" :back="route('sell.import.index')">
        <div class="mx-auto max-w-4xl">
            <div class="surface mb-4 grid grid-cols-3 gap-3 p-4 text-center">
                <div><div class="font-display text-2xl font-extrabold">{{ batch.total ?? '—' }}</div><div class="text-xs text-ink-500">Rows</div></div>
                <div><div class="font-display text-2xl font-extrabold text-brand-700">{{ batch.imported ?? 0 }}</div><div class="text-xs text-ink-500">Imported</div></div>
                <div><div class="font-display text-2xl font-extrabold text-red-600">{{ batch.failed ?? 0 }}</div><div class="text-xs text-ink-500">Failed</div></div>
            </div>
            <p v-if="['queued', 'processing'].includes(batch.status)" class="mb-3 text-sm text-ink-500">Importing… this page refreshes automatically.</p>
            <p v-if="batch.error" class="mb-3 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ batch.error }}</p>
            <div class="mb-3 flex gap-2">
                <a v-if="batch.failed" :href="route('sell.import.errors', batch.id)" class="btn-ghost btn-sm"><Download :size="14" /> Failed rows CSV</a>
                <Link :href="route('sell.dashboard', { status: 'draft' })" class="btn-soft btn-sm">Review drafts</Link>
            </div>
            <div class="surface overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-ink-500"><tr><th class="p-3">Row</th><th class="p-3">Title</th><th class="p-3">Result</th></tr></thead>
                    <tbody class="divide-y divide-ink-100">
                        <tr v-for="r in rows" :key="r.row">
                            <td class="p-3 text-ink-500">{{ r.row }}</td>
                            <td class="p-3"><Link v-if="r.product_id" :href="route('sell.products.edit', r.product_id)" class="hover:underline">{{ r.title }}</Link><span v-else>{{ r.title || '—' }}</span></td>
                            <td class="p-3"><span v-if="!r.errors?.length" class="badge bg-brand-100 capitalize text-brand-800">{{ r.status }}</span><span v-else class="text-xs text-red-600">{{ r.errors.join(' · ') }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppShell>
</template>
