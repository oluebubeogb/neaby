<script setup>
/**
 * Full-bleed, dark "immersive" viewer (Teams/Reels style).
 *  - vertical snap between products, horizontal swipe between a product's photos
 *  - metadata bottom-left, a rail of round actions on the right
 *  - data-light: each slide's details (/p/{slug}/peek) and extra photos load only
 *    when the slide is within one of the visible one; images are lazy + async.
 */
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, ChevronUp, ExternalLink, Heart, Share2, X } from 'lucide-vue-next';
import CartPill from '@/Components/CartPill.vue';
import { cartAdded, flyToCart } from '@/composables/cartFx';
import Avatar from '@/Components/Avatar.vue';
import ReachOut from '@/Components/ReachOut.vue';
import ShareSheet from '@/Components/ShareSheet.vue';
import Tip from '@/Components/Tip.vue';
import CartIcon from '@/Components/icons/CartIcon.vue';
import { requireLogin } from '@/composables/auth';
import { getJson } from '@/composables/http';

const props = defineProps({
    show: Boolean,
    items: { type: Array, default: () => [] }, // product cards: {id, slug, title, price, image, store?, city?}
    start: { type: Number, default: 0 },
    title: { type: String, default: 'For you' },
});
const emit = defineEmits(['close', 'end']);
const page = usePage();

const scroller = ref(null);
const active = ref(0);
const details = reactive({}); // slug → peek
const photo = reactive({}); // slug → active photo index
const saved = reactive({}); // slug → bool
const sharing = ref(null);
let io = null;

const list = computed(() => props.items.filter((p) => p && p.slug));
const cur = computed(() => list.value[active.value]);

async function peek(i) {
    const p = list.value[i];
    if (!p || details[p.slug]) return;
    details[p.slug] = { loading: true };
    try {
        const d = await getJson(route('products.peek', p.slug));
        details[p.slug] = d;
        saved[p.slug] = d.is_saved;
    } catch (_) {
        details[p.slug] = { failed: true };
    }
}
watch(active, (i) => { peek(i); peek(i + 1); peek(i - 1); if (i >= list.value.length - 3) emit('end'); });
// Feed grew while open (infinite scroll) — observe the new slides too.
watch(() => list.value.length, async () => { if (!props.show) return; await nextTick(); observe(); });

function observe() {
    io?.disconnect();
    io = new IntersectionObserver((entries) => {
        for (const e of entries) if (e.isIntersecting && e.intersectionRatio > 0.6) active.value = Number(e.target.dataset.i);
    }, { root: scroller.value, threshold: [0.6] });
    scroller.value?.querySelectorAll('[data-i]').forEach((el) => io.observe(el));
}

watch(() => props.show, async (v) => {
    document.documentElement.classList.toggle('overflow-hidden', v);
    if (!v) { io?.disconnect(); return; }
    active.value = Math.max(0, Math.min(props.start, list.value.length - 1));
    peek(active.value); peek(active.value + 1);
    await nextTick();
    scroller.value?.children[active.value]?.scrollIntoView({ block: 'start' });
    observe();
    scroller.value?.focus();
});
onBeforeUnmount(() => { io?.disconnect(); document.documentElement.classList.remove('overflow-hidden'); });

function go(d) {
    const i = Math.max(0, Math.min(list.value.length - 1, active.value + d));
    scroller.value?.children[i]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function onKey(e) {
    if (e.key === 'Escape') { if (!sharing.value) emit('close'); }
    else if (e.key === 'ArrowDown' || e.key === 'j') { e.preventDefault(); go(1); }
    else if (e.key === 'ArrowUp' || e.key === 'k') { e.preventDefault(); go(-1); }
    else if (e.key === 'ArrowRight') swipe(cur.value, 1);
    else if (e.key === 'ArrowLeft') swipe(cur.value, -1);
}

const photosFor = (p) => (details[p.slug]?.images?.length ? details[p.slug].images : p.image ? [p.image] : []);
function swipe(p, d) {
    if (!p) return;
    const n = photosFor(p).length;
    if (n < 2) return;
    const k = ((photo[p.slug] || 0) + d + n) % n;
    const el = scroller.value?.children[active.value]?.querySelector('[data-strip]');
    el?.scrollTo({ left: k * el.clientWidth, behavior: 'smooth' });
    photo[p.slug] = k;
}
function onPhotoScroll(p, e) {
    const el = e.target;
    photo[p.slug] = Math.round(el.scrollLeft / el.clientWidth);
}

function toggleSave(p) {
    if (!page.props.auth?.user) return requireLogin();
    saved[p.slug] = !saved[p.slug];
    router.post(route('saved.toggle', p.id), {}, { preserveScroll: true, preserveState: true, onError: () => (saved[p.slug] = !saved[p.slug]) });
}
const inCart = (p) => Number(page.props.counts?.cart_items?.[p.id] || 0);
const adding = reactive({}); // slug → true while posting
const justAdded = reactive({}); // slug → true for ~1.6s after success
const toast = ref(null); // { title, image, qty }
let toastT = null;
const layer = ref(null);
function addToCart(p, e) {
    if (!page.props.auth?.user) return requireLogin();
    const d = details[p.slug];
    if (d?.has_options) return router.visit(d.url); // choose size/colour on the product page
    if (adding[p.slug]) return;
    const btn = e?.currentTarget;
    adding[p.slug] = true;
    router.post(route('cart.add', p.id), { quantity: 1 }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            nextTick(() => flyToCart(btn, p.image, layer.value));
            cartAdded(1);
            justAdded[p.slug] = true;
            setTimeout(() => (justAdded[p.slug] = false), 1600);
            toast.value = { title: p.title, image: p.image, qty: inCart(p) };
            clearTimeout(toastT);
            toastT = setTimeout(() => (toast.value = null), 3200);
        },
        onError: (errs) => {
            toast.value = { error: errs.quantity || errs.options || "Couldn't add to cart." };
            clearTimeout(toastT);
            toastT = setTimeout(() => (toast.value = null), 3200);
        },
        onFinish: () => (adding[p.slug] = false),
    });
}
function open(p) { emit('close'); router.visit(details[p.slug]?.url || route('products.show', p.slug)); }
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="show" ref="layer" class="fixed inset-0 z-[60] bg-ink-950/80 text-white backdrop-blur-2xl" role="dialog" aria-modal="true" :aria-label="title">
                <!-- top bar -->
                <div class="pointer-events-none absolute inset-x-0 top-0 z-20 flex items-center gap-3 bg-gradient-to-b from-black/60 to-transparent px-4 pb-10 pt-[calc(env(safe-area-inset-top)+12px)]">
                    <span class="font-brand text-lg font-bold tracking-tight">neaby<span class="text-brand-400">.</span></span>
                    <span class="text-[11px] font-bold uppercase tracking-[.16em] text-white/70">{{ title }}</span>
                    <span class="ml-auto text-xs tabular-nums text-white/60">{{ active + 1 }} / {{ list.length }}</span>
                    <Tip v-if="page.props.auth?.user && page.props.features?.checkout !== false" label="Cart" side="bottom" dark>
                        <CartPill dark class="pointer-events-auto" @click="emit('close')" />
                    </Tip>
                    <Tip label="Close (Esc)" side="bottom" dark>
                        <button class="immersive-btn pointer-events-auto h-10 w-10" aria-label="Close" @click="emit('close')"><X :size="20" /></button>
                    </Tip>
                </div>

                <div ref="scroller" tabindex="-1" class="no-scrollbar h-full snap-y snap-mandatory overflow-y-auto outline-none" @keydown="onKey">
                    <section v-for="(p, i) in list" :key="p.slug" :data-i="i" class="relative h-[100dvh] w-full snap-start overflow-hidden">
                        <!-- photo strip; only near slides render real photos -->
                        <template v-if="Math.abs(i - active) <= 1">
                            <img v-if="p.image" :src="p.image" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-110 object-cover opacity-40 blur-3xl" loading="lazy" decoding="async" />
                            <div class="no-scrollbar absolute inset-0 flex snap-x snap-mandatory overflow-x-auto" data-strip @scroll.passive="onPhotoScroll(p, $event)">
                                <img
                                    v-for="(src, k) in photosFor(p)"
                                    :key="src"
                                    :src="src"
                                    :alt="`${p.title} — photo ${k + 1}`"
                                    class="h-full w-full shrink-0 snap-center object-contain"
                                    :loading="k === 0 && i === active ? 'eager' : 'lazy'"
                                    decoding="async"
                                />
                            </div>
                        </template>

                        <!-- photo dots -->
                        <div v-if="photosFor(p).length > 1" class="absolute inset-x-0 top-[calc(env(safe-area-inset-top)+64px)] z-10 flex justify-center gap-1">
                            <button v-for="(s, k) in photosFor(p)" :key="k" class="h-1.5 rounded-full bg-white transition-all" :class="k === (photo[p.slug] || 0) ? 'w-6' : 'w-2 opacity-40'" :aria-label="`Photo ${k + 1}`" @click="swipe(p, k - (photo[p.slug] || 0))" />
                        </div>

                        <!-- metadata, bottom-left -->
                        <div class="absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-4 pb-[calc(env(safe-area-inset-bottom)+20px)] pr-20 pt-24 md:px-8 md:pr-28">
                            <div class="max-w-xl">
                                <Link v-if="p.store || details[p.slug]?.store" :href="route('stores.show', (details[p.slug]?.store || p.store).slug)" class="inline-flex items-center gap-2 text-sm font-semibold" @click="emit('close')">
                                    <Avatar :src="(details[p.slug]?.store || p.store).logo" :initials="(details[p.slug]?.store || p.store).name?.[0] || '?'" :accent="(details[p.slug]?.store || p.store).accent" :size="30" class="ring-white/30" />
                                    {{ (details[p.slug]?.store || p.store).name }}
                                </Link>
                                <h2 class="mt-2 line-clamp-2 font-display text-lg font-bold leading-snug md:text-xl">{{ p.title }}</h2>
                                <div class="mt-1 flex items-baseline gap-2">
                                    <span class="font-display text-lg font-extrabold text-brand-300">{{ p.price }}</span>
                                    <span v-if="p.compare_at" class="text-sm text-white/50 line-through">{{ p.compare_at }}</span>
                                    <span v-if="p.city || details[p.slug]?.city" class="text-xs text-white/60">· {{ p.city || details[p.slug]?.city }}</span>
                                </div>
                                <p v-if="details[p.slug]?.excerpt" class="mt-2 line-clamp-2 text-[13px] leading-relaxed text-white/75">{{ details[p.slug].excerpt }}</p>
                            </div>
                        </div>

                        <!-- right rail -->
                        <div class="absolute bottom-[calc(env(safe-area-inset-bottom)+24px)] right-3 z-20 flex flex-col items-center gap-3 md:right-6">
                            <Tip :label="saved[p.slug] ? 'Saved' : 'Save'" side="left" dark>
                                <button class="immersive-btn" :aria-pressed="!!saved[p.slug]" aria-label="Save" @click="toggleSave(p)"><Heart :size="22" :class="saved[p.slug] ? 'fill-red-500 text-red-500' : ''" /></button>
                            </Tip>
                            <ReachOut v-if="!details[p.slug]?.is_owner" :product="p.id" :name="(details[p.slug]?.store || p.store)?.name || 'seller'" variant="immersive" :draft="`Hi! Is “${p.title}” still available?`" />
                            <Tip label="Share" side="left" dark>
                                <button class="immersive-btn" aria-label="Share" @click="sharing = p"><Share2 :size="21" /></button>
                            </Tip>
                            <Tip v-if="details[p.slug]?.can_buy && details[p.slug]?.available > 0" :label="details[p.slug]?.has_options ? 'Choose options' : 'Add to cart'" side="left" dark>
                                <button class="immersive-btn relative bg-brand-500/90 ring-brand-300/40 hover:bg-brand-500" :class="justAdded[p.slug] ? 'scale-110 bg-brand-400 ring-4 ring-brand-300/60' : ''" :disabled="adding[p.slug]" aria-label="Add to cart" @click="addToCart(p, $event)">
                                    <Transition mode="out-in" enter-from-class="scale-50 opacity-0" leave-to-class="scale-50 opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
                                        <Check v-if="justAdded[p.slug]" key="ok" :size="22" :stroke-width="3" />
                                        <CartIcon v-else key="cart" :size="22" :class="adding[p.slug] ? 'animate-pulse' : ''" />
                                    </Transition>
                                    <span v-if="inCart(p)" class="absolute -right-1 -top-1 min-w-[20px] rounded-full bg-white px-1 text-center text-[11px] font-extrabold leading-5 text-brand-700 shadow ring-2 ring-brand-500" :aria-label="`${inCart(p)} in cart`">×{{ inCart(p) }}</span>
                                </button>
                            </Tip>
                            <Tip label="Open listing" side="left" dark>
                                <button class="immersive-btn" aria-label="Open listing" @click="open(p)"><ExternalLink :size="20" /></button>
                            </Tip>
                        </div>
                    </section>
                </div>

                <!-- added-to-cart toast (inside the layer so it's never hidden behind it) -->
                <Transition enter-from-class="opacity-0 -translate-y-3 scale-95" leave-to-class="opacity-0 -translate-y-2" enter-active-class="transition duration-300 ease-out" leave-active-class="transition duration-200">
                    <div v-if="toast" class="absolute inset-x-3 top-[calc(env(safe-area-inset-top)+64px)] z-30 mx-auto flex max-w-sm items-center gap-3 rounded-2xl bg-white/95 p-2 pr-3 text-ink-900 shadow-2xl ring-1 ring-black/5 backdrop-blur" role="status" aria-live="polite">
                        <template v-if="!toast.error">
                            <img v-if="toast.image" :src="toast.image" alt="" class="h-11 w-11 shrink-0 rounded-xl object-cover" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1 text-[13px] font-bold text-brand-700"><Check :size="15" :stroke-width="3" /> Added to cart<span v-if="toast.qty > 1" class="font-semibold text-ink-500"> · ×{{ toast.qty }} now</span></div>
                                <div class="truncate text-xs text-ink-500">{{ toast.title }}</div>
                            </div>
                            <Link :href="route('cart.index')" class="btn-sm inline-flex shrink-0 items-center rounded-full bg-brand-600 px-3 font-bold text-white hover:bg-brand-700" @click="emit('close')">View cart</Link>
                        </template>
                        <div v-else class="px-2 py-1.5 text-sm font-semibold text-red-600">{{ toast.error }}</div>
                    </div>
                </Transition>

                <!-- desktop up/down -->
                <div class="absolute left-6 top-1/2 z-20 hidden -translate-y-1/2 flex-col gap-2 md:flex">
                    <button class="immersive-btn h-10 w-10" :disabled="active === 0" aria-label="Previous" @click="go(-1)"><ChevronUp :size="20" /></button>
                    <button class="immersive-btn h-10 w-10" :disabled="active >= list.length - 1" aria-label="Next" @click="go(1)"><ChevronDown :size="20" /></button>
                </div>

            </div>
        </Transition>
        <!-- Share card overlay: rendered OUTSIDE the z-[60] immersive layer (its
             backdrop-blur creates a stacking context) and opened as a modal
             <dialog> (browser top layer, z-[80] fallback) so it always sits above. -->
                <ShareSheet
                    v-if="show && sharing"
                    :show="!!sharing"
                    kind="product"
                    :id="sharing.id"
                    :url="details[sharing.slug]?.url || route('products.show', sharing.slug)"
                    :title="sharing.title"
                    :text="`${sharing.title} — ${sharing.price} on neaby`"
                    :card-base="route('share.product', sharing.slug)"
                    :qr-url="route('qr.product', sharing.slug)"
                    @close="sharing = null"
                />
    </Teleport>
</template>
