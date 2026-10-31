<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Pencil, Plus } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppIcon from '@/Components/AppIcon.vue';
import IconCircle from '@/Components/IconCircle.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';

defineProps({ list: Array, seo: Object });

const editing = ref(null); // null = closed, {} = new, {...} = existing
const form = useForm({ name: '', slug: '', icon: 'tag', accent: '#16b26a', sort: 0, is_active: true });

function open(c = null) {
    form.clearErrors();
    form.defaults(c ? { name: c.name, slug: c.slug, icon: c.icon || 'tag', accent: c.accent || '#16b26a', sort: c.sort, is_active: c.is_active } : { name: '', slug: '', icon: 'tag', accent: '#16b26a', sort: 0, is_active: true });
    form.reset();
    editing.value = c ?? {};
}
function save() {
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    if (editing.value?.id) form.post(route('admin.categories.update', editing.value.id), opts);
    else form.post(route('admin.categories.store'), opts);
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Categories" :back="route('admin.index')">
        <template #actions><button class="btn-primary" @click="open()"><Plus :size="17" /> Add category</button></template>
        <button class="btn-primary mb-4 w-full md:hidden" @click="open()"><Plus :size="17" /> Add category</button>

        <div class="surface divide-y divide-ink-100">
            <div v-for="c in list" :key="c.id" class="flex items-center gap-3 p-3.5">
                <IconCircle :color="c.accent || '#16b26a'" :size="42"><AppIcon :name="c.icon" :size="19" /></IconCircle>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold" :class="!c.is_active && 'text-ink-400 line-through'">{{ c.name }}</div>
                    <div class="text-xs text-ink-500">/c/{{ c.slug }} · {{ c.products_count }} listings · sort {{ c.sort }}</div>
                </div>
                <button class="icon-btn" aria-label="Edit" @click="open(c)"><Pencil :size="17" /></button>
            </div>
        </div>

        <Modal :show="editing !== null" max-width="md" @close="editing = null">
            <form class="space-y-4 p-6" @submit.prevent="save">
                <h2 class="font-display text-base font-bold">{{ editing?.id ? 'Edit category' : 'New category' }}</h2>
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" class="input" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Slug</label>
                        <input v-model="form.slug" class="input" placeholder="auto" />
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div>
                        <label class="label">Lucide icon</label>
                        <div class="relative">
                            <input v-model="form.icon" class="input pr-10" placeholder="tag" />
                            <AppIcon :name="form.icon" :size="17" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-ink-500" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Accent</label>
                        <input v-model="form.accent" type="color" class="h-12 w-full cursor-pointer rounded-2xl border border-ink-200 bg-white p-1" />
                    </div>
                    <div>
                        <label class="label">Sort</label>
                        <input v-model="form.sort" type="number" min="0" class="input" />
                    </div>
                </div>
                <label class="flex items-center gap-2.5 text-sm font-medium"><input v-model="form.is_active" type="checkbox" class="h-5 w-5 rounded-md border-ink-300 text-brand-500" /> Active (visible to buyers)</label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-ghost" @click="editing = null">Cancel</button>
                    <button class="btn-primary" :disabled="form.processing">Save</button>
                </div>
            </form>
        </Modal>
    </AppShell>
</template>
