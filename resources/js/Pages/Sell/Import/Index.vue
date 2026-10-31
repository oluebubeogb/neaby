<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Upload } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import IconCircle from '@/Components/IconCircle.vue';

defineProps({ batches: Array, categories: Array, maxRows: Number, seo: Object });
const form = useForm({ file: null });
const submit = () => form.post(route('sell.import.store'), { forceFormData: true });
const tone = { completed: 'bg-brand-100 text-brand-800', processing: 'bg-sky-100 text-sky-800', queued: 'bg-amber-100 text-amber-800', failed: 'bg-red-100 text-red-700' };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Import products" :back="route('sell.dashboard')">
        <div class="mx-auto grid max-w-3xl gap-4">
            <form class="surface p-5" @submit.prevent="submit">
                <div class="flex items-start gap-3">
                    <IconCircle color="#16b26a" :size="44"><FileSpreadsheet :size="20" /></IconCircle>
                    <div class="text-sm text-ink-600">
                        <h2 class="font-display text-lg font-bold text-ink-900">Upload a CSV or Excel file</h2>
                        <p>Up to {{ maxRows }} rows. Imported products are saved as <b>drafts</b> — add photos, then publish. Include an <code>id</code> column to update existing listings.</p>
                    </div>
                </div>
                <input type="file" accept=".csv,.txt,.xlsx" class="input mt-4" @change="form.file = $event.target.files[0]" />
                <p v-if="form.errors.file" class="mt-1 text-xs text-red-600">{{ form.errors.file }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button class="btn-primary" :disabled="!form.file || form.processing"><Upload :size="16" /> Import</button>
                    <a :href="route('sell.import.template')" class="btn-ghost"><Download :size="16" /> Template CSV</a>
                </div>
                <details class="mt-4 text-sm text-ink-600">
                    <summary class="cursor-pointer font-semibold">Category slugs</summary>
                    <div class="mt-2 flex flex-wrap gap-1"><code v-for="c in categories" :key="c.slug" class="rounded bg-ink-100 px-1.5 py-0.5 text-xs">{{ c.slug }}</code></div>
                </details>
            </form>
            <section v-if="batches.length" class="surface divide-y divide-ink-100 overflow-hidden">
                <Link v-for="b in batches" :key="b.id" :href="route('sell.import.show', b.id)" class="flex items-center gap-3 p-4 text-sm hover:bg-ink-50">
                    <div class="min-w-0 flex-1"><div class="truncate font-medium">{{ b.filename }}</div><div class="text-xs text-ink-500">{{ b.created_at }}</div></div>
                    <span class="text-ink-600">{{ b.imported }}/{{ b.total }} ok<span v-if="b.failed" class="text-red-600"> · {{ b.failed }} failed</span></span>
                    <span class="badge" :class="tone[b.status]">{{ b.status }}</span>
                </Link>
            </section>
        </div>
    </AppShell>
</template>
