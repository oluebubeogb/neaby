<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import { ExternalLink, ImagePlus, Loader2, Sparkles, Star, X } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ product: Object, defaults: Object, categoryOptions: Array, states: Array, limits: Object, seo: Object });
const editing = computed(() => !!props.product);

const form = useForm({
    title: props.product?.title ?? '',
    title_ai_generated: props.product?.title_ai_generated ?? false,
    description: props.product?.description ?? '',
    price: props.product?.price ?? '',
    compare_at: props.product?.compare_at ?? '',
    category_id: props.product?.category_id ?? null,
    condition: props.product?.condition ?? 'new',
    quantity: props.product?.quantity ?? 1,
    city: props.product?.city ?? props.defaults?.city ?? '',
    state: props.product?.state ?? props.defaults?.state ?? '',
    images: [],
    remove_images: [],
    intent: 'publish',
    checkout_enabled: props.product?.checkout_enabled ?? true,
    pickup_available: props.product?.pickup_available ?? false,
    delivery_fee: props.product?.delivery_fee ?? '',
});

// ---- AI description + pricing help -------------------------------------
const describing = ref(false);
const descError = ref('');
async function postJson(url, body) {
    const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
    if (res.status === 429) throw new Error('Too many requests — try again in a minute.');
    if (!res.ok) throw new Error('Not available right now.');
    return res.json();
}
async function improveDescription() {
    descError.value = '';
    if (form.description.trim().length < 4) { descError.value = 'Jot a few notes first (size, condition, what makes it special).'; return; }
    describing.value = true;
    try {
        const j = await postJson(route('sell.ai.description'), { title: form.title || null, notes: form.description, category_id: form.category_id, condition: form.condition, city: form.city || null });
        if (j.enabled === false) throw new Error('AI is switched off — your own words work great too.');
        if (j.description) form.description = j.description;
    } catch (e) { descError.value = e.message; } finally { describing.value = false; }
}
const pricing = ref(null);
const pricingBusy = ref(false);
async function priceHelp() {
    pricingBusy.value = true;
    try { pricing.value = await postJson(route('sell.pricing'), { category_id: form.category_id, condition: form.condition, price: form.price || null, product_id: props.product?.id || null }); }
    catch (e) { pricing.value = { available: false, message: e.message }; } finally { pricingBusy.value = false; }
}

// ---- Photos -------------------------------------------------------------
const existing = ref([...(props.product?.images ?? [])]);
const fresh = ref([]); // { file, url }
const total = computed(() => existing.value.length + fresh.value.length);
const room = computed(() => props.limits.max_images - total.value);
const dragging = ref(false);
const photoError = ref('');

/** Downscale big phone photos client-side so uploads stay fast on mobile data. */
async function shrink(file, max = 1800) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return file;
    try {
        const bmp = await createImageBitmap(file);
        const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
        if (scale === 1 && file.size < 1.5 * 1024 * 1024) return file;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bmp.width * scale);
        canvas.height = Math.round(bmp.height * scale);
        canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.85));
        return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
    } catch (_) {
        return file;
    }
}

async function addFiles(list) {
    photoError.value = '';
    const files = [...list].filter((f) => f.type.startsWith('image/'));
    if (files.length > room.value) photoError.value = `You can add ${room.value} more photo${room.value === 1 ? '' : 's'}.`;
    for (const f of files.slice(0, Math.max(0, room.value))) {
        const small = await shrink(f);
        if (small.size > props.limits.max_image_kb * 1024) {
            photoError.value = `${f.name} is too large (max ${Math.round(props.limits.max_image_kb / 1024)} MB).`;
            continue;
        }
        fresh.value.push({ file: small, url: URL.createObjectURL(small) });
    }
}
function onPick(e) { addFiles(e.target.files); e.target.value = ''; }
function onDrop(e) { dragging.value = false; addFiles(e.dataTransfer.files); }
function removeExisting(img) {
    form.remove_images.push(img.id);
    existing.value = existing.value.filter((i) => i.id !== img.id);
}
function removeFresh(i) {
    URL.revokeObjectURL(fresh.value[i].url);
    fresh.value.splice(i, 1);
}
function makeCover(i) {
    const [item] = fresh.value.splice(i, 1);
    fresh.value.unshift(item);
}
onBeforeUnmount(() => fresh.value.forEach((f) => URL.revokeObjectURL(f.url)));

// ---- AI titles ----------------------------------------------------------
const suggestions = ref([]);
const suggesting = ref(false);
const aiError = ref('');

function xsrf() {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}
async function suggest() {
    aiError.value = '';
    if (form.description.trim().length < 10) {
        aiError.value = 'Write a short description first — the AI uses it to suggest titles.';
        return;
    }
    suggesting.value = true;
    try {
        const res = await fetch(route('sell.ai.titles'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ description: form.description, category_id: form.category_id, condition: form.condition, city: form.city || null }),
        });
        if (res.status === 429) throw new Error('Too many requests — try again in a minute.');
        if (!res.ok) throw new Error('Could not get suggestions right now.');
        const json = await res.json();
        if (json.enabled === false) throw new Error('AI suggestions are switched off — just type a title.');
        suggestions.value = json.titles ?? [];
    } catch (e) {
        aiError.value = e.message;
    } finally {
        suggesting.value = false;
    }
}
function useTitle(t) {
    form.title = t;
    form.title_ai_generated = true;
    suggestions.value = [];
}

// ---- Submit -------------------------------------------------------------
function submit(intent) {
    form.intent = intent;
    form.images = fresh.value.map((f) => f.file);
    const opts = { forceFormData: true, preserveScroll: true };
    form.transform((d) => ({ ...d, title_ai_generated: d.title_ai_generated ? 1 : 0, checkout_enabled: d.checkout_enabled ? 1 : 0, pickup_available: d.pickup_available ? 1 : 0 }));
    if (editing.value) form.post(route('sell.products.update', props.product.id), opts);
    else form.post(route('sell.products.store'), opts);
}

const imagesError = computed(() => form.errors.images || Object.entries(form.errors).find(([k]) => k.startsWith('images.'))?.[1]);
const isLive = computed(() => props.product?.status === 'published');
const reviewNote = computed(() => props.product?.status === 'rejected' && props.product?.moderation_note);
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :title="editing ? 'Edit listing' : 'New listing'" :back="route('sell.dashboard')">
        <template #actions>
            <a v-if="editing" :href="product.url" target="_blank" class="btn-ghost btn-sm"><ExternalLink :size="15" /> Preview</a>
        </template>

        <form class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_340px]" @submit.prevent="submit(isLive ? 'draft' : 'publish')">
            <div class="space-y-6">
                <div v-if="reviewNote" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><b>Changes requested:</b> {{ reviewNote }}</div>

                <!-- Photos -->
                <section class="surface p-5 md:p-6">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-display text-lg font-bold">Photos</h2>
                        <span class="text-sm text-ink-500">{{ total }}/{{ limits.max_images }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-4">
                        <div v-for="(img, i) in existing" :key="'e' + img.id" class="group relative aspect-square overflow-hidden rounded-2xl bg-ink-100">
                            <img :src="img.url" alt="" class="h-full w-full object-cover" />
                            <span v-if="i === 0" class="absolute left-1.5 top-1.5 badge bg-white/90 text-ink-800">Cover</span>
                            <button type="button" class="absolute right-1.5 top-1.5 flex h-7 w-7 items-center justify-center rounded-full bg-ink-950/70 text-white" aria-label="Remove photo" @click="removeExisting(img)"><X :size="14" /></button>
                        </div>
                        <div v-for="(f, i) in fresh" :key="f.url" class="group relative aspect-square overflow-hidden rounded-2xl bg-ink-100">
                            <img :src="f.url" alt="" class="h-full w-full object-cover" />
                            <span v-if="!existing.length && i === 0" class="absolute left-1.5 top-1.5 badge bg-white/90 text-ink-800">Cover</span>
                            <button v-else-if="!existing.length" type="button" class="absolute bottom-1.5 left-1.5 flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-ink-700 opacity-0 transition group-hover:opacity-100" aria-label="Make cover" @click="makeCover(i)"><Star :size="14" /></button>
                            <button type="button" class="absolute right-1.5 top-1.5 flex h-7 w-7 items-center justify-center rounded-full bg-ink-950/70 text-white" aria-label="Remove photo" @click="removeFresh(i)"><X :size="14" /></button>
                        </div>
                        <label
                            v-if="room > 0"
                            class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed text-center text-sm transition"
                            :class="dragging ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-ink-200 text-ink-500 hover:border-brand-300 hover:bg-brand-50/50'"
                            @dragover.prevent="dragging = true"
                            @dragleave="dragging = false"
                            @drop.prevent="onDrop"
                        >
                            <ImagePlus :size="24" />
                            <span class="font-medium">Add photos</span>
                            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onPick" />
                        </label>
                    </div>
                    <p class="hint">Bright, natural-light photos sell faster. The first photo is your cover.</p>
                    <InputError :message="photoError || imagesError" />
                </section>

                <!-- Details -->
                <section class="surface space-y-5 p-5 md:p-6">
                    <div>
                        <label class="label" for="description">Describe it</label>
                        <textarea id="description" v-model="form.description" rows="5" class="input" maxlength="5000" placeholder="e.g. vintage oversized denim jacket, size M, barely worn, light wash" />
                        <div class="mt-1.5 flex items-center gap-2">
                            <button type="button" class="btn-soft btn-sm" :disabled="describing" @click="improveDescription"><Loader2 v-if="describing" :size="15" class="animate-spin" /><Sparkles v-else :size="15" /> Write it up for me</button>
                            <span v-if="descError" class="text-sm text-amber-700">{{ descError }}</span>
                        </div>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="label mb-0" for="title">Title <span class="font-normal text-ink-400">(optional)</span></label>
                            <button type="button" class="btn-soft btn-sm" :disabled="suggesting" @click="suggest">
                                <Loader2 v-if="suggesting" :size="15" class="animate-spin" /><Sparkles v-else :size="15" /> Suggest titles
                            </button>
                        </div>
                        <input id="title" v-model="form.title" class="input" maxlength="140" placeholder="Leave blank and we'll write one for you" @input="form.title_ai_generated = false" />
                        <div v-if="suggestions.length" class="mt-2 space-y-1.5">
                            <button v-for="t in suggestions" :key="t" type="button" class="flex w-full items-center gap-2 rounded-2xl border border-brand-100 bg-brand-50/60 px-3 py-2.5 text-left text-sm font-medium text-ink-800 transition hover:border-brand-300 hover:bg-brand-50" @click="useTitle(t)">
                                <Sparkles :size="14" class="shrink-0 text-brand-600" /> {{ t }}
                            </button>
                        </div>
                        <p v-if="aiError" class="mt-1.5 text-sm text-amber-700">{{ aiError }}</p>
                        <InputError :message="form.errors.title" />
                    </div>

                    <div>
                        <span class="label">Category</span>
                        <div class="no-scrollbar -mx-1 flex flex-wrap gap-2 px-1">
                            <button v-for="c in categoryOptions" :key="c.id" type="button" class="chip" :class="form.category_id === c.id && 'chip-active'" @click="form.category_id = c.id">
                                <AppIcon :name="c.icon" :size="14" /> {{ c.name }}
                            </button>
                        </div>
                        <InputError :message="form.errors.category_id" />
                    </div>

                    <div>
                        <span class="label">Condition</span>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="c in ['new', 'used', 'refurbished']" :key="c" type="button" class="chip justify-center capitalize" :class="form.condition === c && 'chip-active'" @click="form.condition = c">{{ c }}</button>
                        </div>
                        <InputError :message="form.errors.condition" />
                    </div>
                </section>
            </div>

            <!-- Side column: price, stock, location, actions -->
            <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
                <section class="surface space-y-5 p-5 md:p-6">
                    <div>
                        <label class="label" for="price">Price</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-semibold text-ink-500">₦</span>
                            <input id="price" v-model="form.price" type="number" min="1" step="0.01" inputmode="decimal" class="input pl-9 text-lg font-semibold" placeholder="0" required />
                        </div>
                        <InputError :message="form.errors.price" />
                        <button type="button" class="mt-1.5 text-xs font-semibold text-brand-700 hover:underline" :disabled="pricingBusy" @click="priceHelp">{{ pricingBusy ? 'Checking…' : 'What do similar items sell for?' }}</button>
                        <div v-if="pricing" class="mt-1.5 rounded-xl bg-ink-50 p-2.5 text-xs text-ink-700">
                            <div v-if="pricing.available" class="font-semibold">Typical {{ pricing.low }} – {{ pricing.high }} · median {{ pricing.median }} <span class="font-normal text-ink-400">({{ pricing.sample }} listings)</span></div>
                            {{ pricing.message }}
                        </div>
                    </div>
                    <div>
                        <label class="label" for="compare_at">Was <span class="font-normal text-ink-400">(optional, shows a discount)</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-400">₦</span>
                            <input id="compare_at" v-model="form.compare_at" type="number" min="1" step="0.01" inputmode="decimal" class="input pl-9" />
                        </div>
                        <InputError :message="form.errors.compare_at" />
                    </div>
                    <div>
                        <label class="label" for="quantity">Quantity in stock</label>
                        <input id="quantity" v-model="form.quantity" type="number" min="0" inputmode="numeric" class="input" />
                        <InputError :message="form.errors.quantity" />
                    </div>
                    <div class="space-y-2 rounded-2xl bg-ink-50 p-3">
                        <label class="flex items-center gap-2 text-sm font-medium"><input v-model="form.checkout_enabled" type="checkbox" class="rounded text-brand-600" /> Buyers can pay on neaby</label>
                        <label class="flex items-center gap-2 text-sm font-medium"><input v-model="form.pickup_available" type="checkbox" class="rounded text-brand-600" /> Pickup available</label>
                        <div>
                            <label class="label" for="delivery_fee">Delivery fee ₦ <span class="font-normal text-ink-400">(blank = free / arranged)</span></label>
                            <input id="delivery_fee" v-model="form.delivery_fee" type="number" min="0" step="0.01" inputmode="decimal" class="input" />
                            <InputError :message="form.errors.delivery_fee" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                        <div>
                            <label class="label" for="city">City</label>
                            <input id="city" v-model="form.city" class="input" />
                        </div>
                        <div>
                            <label class="label" for="state">State</label>
                            <select id="state" v-model="form.state" class="input">
                                <option value="">Select</option>
                                <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <InputError :message="form.errors.state" />
                        </div>
                    </div>
                </section>

                <div class="sticky bottom-[calc(4.75rem+var(--safe-bottom))] z-20 flex gap-3 rounded-3xl border border-ink-200/70 bg-white/90 p-3 shadow-lift backdrop-blur md:bottom-4 lg:static lg:flex-col lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
                    <button v-if="!isLive" type="button" class="btn-ghost flex-1" :disabled="form.processing" @click="submit('draft')">Save draft</button>
                    <button type="submit" class="btn-primary flex-[1.4]" :disabled="form.processing">
                        <Loader2 v-if="form.processing" :size="17" class="animate-spin" />
                        {{ isLive ? 'Save changes' : 'Publish' }}
                    </button>
                </div>
                <p v-if="form.progress" class="text-center text-xs text-ink-500">Uploading… {{ form.progress.percentage }}%</p>
            </aside>
        </form>
    </AppShell>
</template>
