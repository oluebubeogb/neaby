<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Camera, ImagePlus, Instagram, MessageCircle, X } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ store: Object, suggestedSlug: String, states: Array, seo: Object });
const editing = computed(() => !!props.store);
const accents = ['#16b26a', '#0891b2', '#4f46e5', '#7c3aed', '#db2777', '#e11d48', '#ea580c', '#d97706', '#0f766e', '#334155'];

const form = useForm({
    name: props.store?.name ?? '',
    slug: props.store?.slug ?? props.suggestedSlug ?? '',
    tagline: props.store?.tagline ?? '',
    description: props.store?.description ?? '',
    accent: props.store?.accent ?? '#16b26a',
    city: props.store?.city ?? '',
    state: props.store?.state ?? '',
    whatsapp: props.store?.whatsapp ?? '',
    instagram: props.store?.instagram ?? '',
    logo: null,
    cover: null,
    remove_logo: false,
    remove_cover: false,
});

const logoPreview = ref(props.store?.logo ?? null);
const coverPreview = ref(props.store?.cover ?? null);
const slugTouched = ref(editing.value);

function onName() {
    if (!slugTouched.value) form.slug = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30) || props.suggestedSlug;
}
function onSlug(e) {
    slugTouched.value = true;
    form.slug = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 30);
}
function pick(field, e) {
    const file = e.target.files?.[0];
    if (!file) return;
    form[field] = file;
    form['remove_' + field] = false;
    (field === 'logo' ? logoPreview : coverPreview).value = URL.createObjectURL(file);
}
function clear(field) {
    form[field] = null;
    form['remove_' + field] = true;
    (field === 'logo' ? logoPreview : coverPreview).value = null;
}
const initials = computed(() => (form.name || '?').charAt(0).toUpperCase());
const host = computed(() => route('home').replace(/^https?:\/\//, '').replace(/\/home$/, ''));

function submit() {
    form.transform((d) => ({ ...d, remove_logo: d.remove_logo ? 1 : 0, remove_cover: d.remove_cover ? 1 : 0 }))
        .post(editing.value ? route('sell.store.update') : route('sell.store.store'), { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="editing ? 'Store settings' : 'Open your store'" :back="route('sell.dashboard')">
        <form class="mx-auto max-w-2xl space-y-6" @submit.prevent="submit">
            <!-- Brand preview -->
            <section class="surface overflow-hidden">
                <div class="relative h-36 md:h-44" :style="{ background: `linear-gradient(135deg, ${form.accent}, ${form.accent}99 55%, #0a4b31)` }">
                    <img v-if="coverPreview" :src="coverPreview" alt="" class="h-full w-full object-cover" />
                    <div class="absolute right-3 top-3 flex gap-2">
                        <label class="btn-sm btn cursor-pointer bg-white/90 text-ink-800 backdrop-blur hover:bg-white"><ImagePlus :size="15" /> Cover<input type="file" accept="image/*" class="hidden" @change="pick('cover', $event)" /></label>
                        <button v-if="coverPreview" type="button" class="icon-btn bg-white/90" aria-label="Remove cover" @click="clear('cover')"><X :size="16" /></button>
                    </div>
                </div>
                <div class="flex items-end gap-4 px-5 pb-5">
                    <div class="relative -mt-10">
                        <Avatar :src="logoPreview" :initials="initials" :accent="form.accent" :size="88" rounded="rounded-[1.5rem]" class="shadow-lift ring-4 ring-white" />
                        <label class="absolute -bottom-1 -right-1 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-ink-900 text-white shadow-lift" aria-label="Upload logo"><Camera :size="16" /><input type="file" accept="image/*" class="hidden" @change="pick('logo', $event)" /></label>
                    </div>
                    <div class="min-w-0 pb-1">
                        <div class="truncate font-display text-lg font-bold">{{ form.name || 'Your store name' }}</div>
                        <div class="truncate text-sm text-ink-500">{{ host }}/s/{{ form.slug || 'handle' }}</div>
                        <button v-if="logoPreview" type="button" class="text-xs font-semibold text-red-600" @click="clear('logo')">Remove logo</button>
                    </div>
                </div>
                <div class="px-5 pb-5">
                    <InputError :message="form.errors.logo || form.errors.cover" />
                </div>
            </section>

            <section class="surface space-y-5 p-5 md:p-6">
                <div>
                    <label class="label" for="name">Store name</label>
                    <input id="name" v-model="form.name" class="input" maxlength="80" required placeholder="e.g. Ada's Closet" @input="onName" />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <label class="label" for="slug">Store link</label>
                    <div class="flex items-stretch overflow-hidden rounded-2xl border border-ink-200 bg-white focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-100">
                        <span class="flex items-center bg-ink-50 px-3 text-sm text-ink-500">/s/</span>
                        <input id="slug" :value="form.slug" class="min-w-0 flex-1 border-0 px-3 py-3 focus:ring-0" required @input="onSlug" />
                    </div>
                    <p class="hint">Letters, numbers and dashes. {{ editing ? 'Changing it breaks links you have already shared.' : '' }}</p>
                    <InputError :message="form.errors.slug" />
                </div>
                <div>
                    <label class="label" for="tagline">Tagline <span class="font-normal text-ink-400">(optional)</span></label>
                    <input id="tagline" v-model="form.tagline" class="input" maxlength="120" placeholder="Thrift & vintage, delivered in Lagos" />
                    <InputError :message="form.errors.tagline" />
                </div>
                <div>
                    <label class="label" for="description">About <span class="font-normal text-ink-400">(optional)</span></label>
                    <textarea id="description" v-model="form.description" rows="4" class="input" maxlength="2000" placeholder="What you sell, delivery areas, opening hours…" />
                    <InputError :message="form.errors.description" />
                </div>
                <div>
                    <span class="label">Accent colour</span>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="c in accents" :key="c" type="button" class="h-9 w-9 rounded-full ring-offset-2 transition" :class="form.accent === c ? 'ring-2 ring-ink-900' : 'hover:scale-110'" :style="{ background: c }" :aria-label="c" @click="form.accent = c" />
                    </div>
                </div>
            </section>

            <section class="surface grid gap-5 p-5 md:grid-cols-2 md:p-6">
                <div>
                    <label class="label" for="city">City</label>
                    <input id="city" v-model="form.city" class="input" placeholder="Ikeja" />
                    <InputError :message="form.errors.city" />
                </div>
                <div>
                    <label class="label" for="state">State</label>
                    <select id="state" v-model="form.state" class="input">
                        <option value="">Select state</option>
                        <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <InputError :message="form.errors.state" />
                </div>
                <div>
                    <label class="label" for="whatsapp"><MessageCircle :size="14" class="mr-1 inline text-brand-600" />WhatsApp number</label>
                    <input id="whatsapp" v-model="form.whatsapp" class="input" inputmode="tel" placeholder="0803 123 4567" />
                    <p class="hint">Buyers tap “Chat seller” to message you.</p>
                    <InputError :message="form.errors.whatsapp" />
                </div>
                <div>
                    <label class="label" for="instagram"><Instagram :size="14" class="mr-1 inline text-pink-600" />Instagram</label>
                    <input id="instagram" v-model="form.instagram" class="input" placeholder="@yourstore" />
                    <InputError :message="form.errors.instagram" />
                </div>
            </section>

            <div class="sticky bottom-[calc(4.75rem+var(--safe-bottom))] z-20 flex gap-3 rounded-3xl border border-ink-200/70 bg-white/90 p-3 shadow-lift backdrop-blur md:static md:border-0 md:bg-transparent md:p-0 md:shadow-none">
                <Link :href="route('sell.dashboard')" class="btn-ghost flex-1 md:flex-none">Cancel</Link>
                <button class="btn-primary flex-[2] md:ml-auto md:flex-none md:px-8" :disabled="form.processing">{{ form.processing ? 'Saving…' : editing ? 'Save changes' : 'Open store' }}</button>
            </div>
        </form>
    </AppShell>
</template>
