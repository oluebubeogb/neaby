<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { BadgeCheck, Eye, ImageOff, Instagram, MapPin, MessageCircle, Package, Pencil, Repeat2, Share2, ShoppingBag, Sparkles, Store, Truck, Zap } from 'lucide-vue-next';
import ShareSheet from '@/Components/ShareSheet.vue';
import SaveButton from '@/Components/SaveButton.vue';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import Avatar from '@/Components/Avatar.vue';
import IconCircle from '@/Components/IconCircle.vue';
import ProductGrid from '@/Components/ProductGrid.vue';

const props = defineProps({ product: Object, isOwner: Boolean, isSaved: Boolean, canBuy: Boolean, resale: Object, resaleAvailable: Boolean, shareUrl: String, moreFromStore: Array, similar: Array, seo: Object });
const page = usePage();
const signedIn = computed(() => !!page.props.auth?.user);
const qty = ref(1);
const busy = ref(false);
function addToCart(buyNow = false) {
    if (!signedIn.value) return router.visit(route('login'));
    busy.value = true;
    router.post(route('cart.add', props.product.id), { quantity: qty.value, buy_now: buyNow ? 1 : 0 }, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
const reqForm = useForm({ request_type: 'availability', requested_quantity: 1, fulfilment_method: '', message: '' });
const fulfilmentLabels = { reseller_collects: 'I collect / pay you directly', supplier_dispatches: 'You dispatch to my buyer' };
function sendRequest() {
    if (!reqForm.fulfilment_method) reqForm.fulfilment_method = props.resale?.allowed_fulfilment?.[0] || 'reseller_collects';
    reqForm.post(route('requests.store', props.product.id), { preserveScroll: true });
}

const active = ref(0);
const strip = ref(null);
const images = computed(() => props.product.images || []);
const store = computed(() => props.product.store);

function onScroll(e) {
    const el = e.target;
    active.value = Math.round(el.scrollLeft / el.clientWidth);
}
function goTo(i) {
    active.value = i;
    strip.value?.scrollTo({ left: i * strip.value.clientWidth, behavior: 'smooth' });
}

const waLink = computed(() => {
    if (!store.value?.whatsapp) return null;
    let n = store.value.whatsapp.replace(/[^0-9]/g, '');
    if (n.startsWith('0')) n = '234' + n.slice(1);
    const text = `Hi ${store.value.name}! I'm interested in “${props.product.title}” (${props.product.price}) on neaby: ${props.product.url}`;
    return `https://wa.me/${n}?text=${encodeURIComponent(text)}`;
});

const sharing = ref(false);
const copied = ref(false);
function share() { sharing.value = true; }

const statusTone = { draft: 'bg-ink-100 text-ink-700', pending: 'bg-amber-100 text-amber-800', rejected: 'bg-red-100 text-red-700', paused: 'bg-ink-100 text-ink-700', archived: 'bg-ink-100 text-ink-500', published: 'bg-brand-100 text-brand-800' };
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell :back="route('home')" title="" wide>
        <template #mobile-actions>
            <SaveButton v-if="!isOwner" :product-id="product.id" :saved="isSaved" />
            <button class="icon-btn" aria-label="Share" @click="share"><Share2 :size="19" /></button>
        </template>

        <div v-if="isOwner && product.status !== 'published'" class="mb-4 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="badge" :class="statusTone[product.status]">{{ product.status }}</span>
            Only you can see this preview.
            <span v-if="product.moderation_note" class="font-medium">Note: {{ product.moderation_note }}</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-10">
            <!-- Gallery -->
            <section class="-mx-3 sm:mx-0 lg:sticky lg:top-6 lg:self-start">
                <div class="relative overflow-hidden bg-ink-100 sm:rounded-[2rem]">
                    <div v-if="images.length" ref="strip" class="no-scrollbar flex aspect-square snap-x snap-mandatory overflow-x-auto" @scroll.passive="onScroll">
                        <img
                            v-for="(img, i) in images"
                            :key="img.id"
                            :src="img.url"
                            :alt="`${product.title} — photo ${i + 1}`"
                            class="h-full w-full shrink-0 snap-center object-cover"
                            :loading="i === 0 ? 'eager' : 'lazy'"
                            :fetchpriority="i === 0 ? 'high' : 'auto'"
                        />
                    </div>
                    <div v-else class="flex aspect-square items-center justify-center text-ink-300"><ImageOff :size="40" /></div>
                    <div v-if="images.length > 1" class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5">
                        <button v-for="(img, i) in images" :key="img.id" class="h-1.5 rounded-full bg-white/90 shadow transition-all" :class="i === active ? 'w-5' : 'w-1.5 opacity-60'" :aria-label="`Photo ${i + 1}`" @click="goTo(i)" />
                    </div>
                </div>
                <div v-if="images.length > 1" class="mt-3 hidden gap-2 px-3 sm:flex sm:px-0">
                    <button v-for="(img, i) in images" :key="img.id" class="h-16 w-16 overflow-hidden rounded-2xl ring-2 transition md:h-20 md:w-20" :class="i === active ? 'ring-brand-500' : 'ring-transparent opacity-70 hover:opacity-100'" @click="goTo(i)">
                        <img :src="img.url" alt="" class="h-full w-full object-cover" loading="lazy" />
                    </button>
                </div>
            </section>

            <!-- Details -->
            <section class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <Link v-if="product.category" :href="route('categories.show', product.category.slug)" class="badge bg-ink-100 text-ink-700 hover:bg-ink-200">{{ product.category.name }}</Link>
                    <span class="badge bg-ink-100 capitalize text-ink-700">{{ product.condition }}</span>
                    <span v-if="product.in_stock" class="badge bg-brand-100 text-brand-800">In stock</span>
                    <span v-else class="badge bg-red-100 text-red-700">Sold out</span>
                </div>

                <h1 class="mt-3 font-display text-2xl font-extrabold leading-tight tracking-tight text-ink-900 md:text-[2rem]">{{ product.title }}</h1>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="font-display text-3xl font-extrabold text-ink-900">{{ product.price }}</span>
                    <span v-if="product.compare_at" class="text-lg text-ink-400 line-through">{{ product.compare_at }}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-500">
                    <span v-if="product.city || product.state" class="inline-flex items-center gap-1"><MapPin :size="14" /> {{ [product.city, product.state].filter(Boolean).join(', ') }}</span>
                    <span class="inline-flex items-center gap-1"><Eye :size="14" /> {{ product.views }} views</span>
                    <span v-if="product.quantity > 0 && product.quantity <= 5" class="font-medium text-amber-700">Only {{ product.quantity }} left</span>
                </div>

                <!-- Actions -->
                <div v-if="canBuy && product.in_stock" class="surface mt-6 p-4">
                    <div class="flex flex-wrap items-center gap-3 text-sm text-ink-600">
                        <span v-if="product.delivery_fee" class="inline-flex items-center gap-1.5"><Truck :size="15" /> Delivery {{ product.delivery_fee }}</span>
                        <span v-else class="inline-flex items-center gap-1.5"><Truck :size="15" /> Delivery fee set by seller at checkout</span>
                        <span v-if="product.pickup_available" class="inline-flex items-center gap-1.5"><Store :size="15" /> Pickup available</span>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <label class="sr-only" for="qty">Quantity</label>
                        <select id="qty" v-model.number="qty" class="input w-24">
                            <option v-for="n in Math.min(product.available || 1, 20)" :key="n" :value="n">{{ n }}</option>
                        </select>
                        <button class="btn-dark btn-lg flex-1" :disabled="busy" @click="addToCart(true)"><Zap :size="18" /> Buy now</button>
                        <button class="btn-ghost btn-lg" :disabled="busy" aria-label="Add to cart" @click="addToCart(false)"><ShoppingBag :size="18" /><span class="hidden sm:inline">Add to cart</span></button>
                    </div>
                    <p class="hint mt-2">Pay securely on neaby. Stock is held for you while you pay.</p>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
                    <Link v-if="isOwner" :href="route('sell.products.edit', product.id)" class="btn-dark btn-lg"><Pencil :size="18" /> Edit listing</Link>
                    <a v-else-if="waLink" :href="waLink" target="_blank" rel="noopener" class="btn-lg" :class="canBuy ? 'btn-soft' : 'btn-primary'"><MessageCircle :size="18" /> Chat seller on WhatsApp</a>
                    <Link v-else-if="store" :href="route('stores.show', store.slug)" class="btn-primary btn-lg"><Store :size="18" /> Visit store</Link>
                    <SaveButton v-if="!isOwner" :product-id="product.id" :saved="isSaved" label />
                    <button class="btn-ghost btn-lg" @click="share"><Share2 :size="18" /> Share</button>
                </div>
                <p v-if="!canBuy && !isOwner" class="mt-2 text-xs text-ink-400">This seller hasn't turned on neaby checkout — arrange payment and delivery directly with them.</p>
                <div v-if="isOwner" class="mt-2 flex flex-wrap gap-3 text-xs text-ink-500">
                    <span>{{ product.saves }} saves</span><span>{{ product.shares }} shares</span>
                    <Link :href="route('sell.promote', product.id)" class="font-semibold text-brand-700 hover:underline">Promote this listing →</Link>
                </div>

                <!-- Resale (protected terms only arrive for eligible viewers) -->
                <div v-if="resale" class="surface mt-6 border border-violet-200 p-4">
                    <div class="flex items-center gap-2 font-display font-bold text-violet-900"><Repeat2 :size="18" /> Resale terms <span class="badge bg-violet-100 text-violet-800">Members only</span></div>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-ink-500">Supplier price</dt><dd class="font-display text-lg font-extrabold">{{ resale.supplier_price }}</dd></div>
                        <div><dt class="text-ink-500">Retail margin</dt><dd class="font-display text-lg font-extrabold" :class="resale.margin.negative ? 'text-red-600' : 'text-brand-700'">{{ resale.margin.gross }} <span v-if="resale.margin.percent !== null" class="text-sm font-semibold">({{ resale.margin.percent }}%)</span></dd></div>
                        <div v-if="resale.min_price"><dt class="text-ink-500">Minimum resale price</dt><dd class="font-semibold">{{ resale.min_price }}</dd></div>
                        <div><dt class="text-ink-500">Fulfilment</dt><dd class="font-semibold">{{ (resale.allowed_fulfilment || []).map((f) => fulfilmentLabels[f]).join(' · ') }}</dd></div>
                    </dl>
                    <p v-if="resale.terms" class="mt-3 whitespace-pre-line rounded-xl bg-violet-50 p-3 text-sm text-violet-900">{{ resale.terms }}</p>
                    <template v-if="!isOwner">
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link v-if="resale.my_offer" :href="route('resell.offers.edit', resale.my_offer.id)" class="btn-dark">Edit my offer</Link>
                            <Link v-else :href="route('resell.offers.create', product.id)" class="btn-dark"><Repeat2 :size="16" /> Resell this</Link>
                        </div>
                        <form class="mt-4 grid gap-2 border-t border-violet-100 pt-4" @submit.prevent="sendRequest">
                            <div class="text-sm font-semibold">Ask the supplier</div>
                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="reqForm.request_type" class="input"><option value="availability">Is it available?</option><option value="stock">Hold stock for me</option><option value="delivery">Delivery question</option><option value="question">Other question</option></select>
                                <input v-model.number="reqForm.requested_quantity" type="number" min="1" class="input" aria-label="Quantity" />
                            </div>
                            <select v-model="reqForm.fulfilment_method" class="input"><option value="">Fulfilment…</option><option v-for="f in resale.allowed_fulfilment" :key="f" :value="f">{{ fulfilmentLabels[f] }}</option></select>
                            <textarea v-model="reqForm.message" rows="2" class="input" placeholder="Message (optional)" />
                            <p v-for="(e, k) in reqForm.errors" :key="k" class="text-xs text-red-600">{{ e }}</p>
                            <button class="btn-soft" :disabled="reqForm.processing">Send request</button>
                        </form>
                    </template>
                </div>
                <Link v-else-if="resaleAvailable" :href="route('membership.index')" class="surface mt-6 flex items-center gap-3 border border-violet-200 p-4 transition hover:shadow-lift">
                    <IconCircle color="#7c3aed" :size="40"><Repeat2 :size="18" /></IconCircle>
                    <div class="text-sm"><div class="font-semibold">Resell this product</div><div class="text-ink-500">Reseller members see the supplier price and earn a margin.</div></div>
                </Link>

                <!-- Store card -->
                <Link v-if="store" :href="route('stores.show', store.slug)" class="surface mt-6 flex items-center gap-3 p-4 transition hover:shadow-lift">
                    <Avatar :src="store.logo" :initials="store.initials" :accent="store.accent" :size="52" rounded="rounded-2xl" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 font-display font-bold text-ink-900">{{ store.name }} <BadgeCheck :size="16" class="text-brand-500" /></div>
                        <div class="truncate text-sm text-ink-500">{{ store.tagline || `On neaby since ${store.joined}` }}</div>
                    </div>
                    <span class="btn-soft btn-sm">View store</span>
                </Link>
                <a v-if="store?.instagram" :href="`https://instagram.com/${store.instagram.replace('@', '')}`" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1.5 text-sm text-ink-500 hover:text-ink-800"><Instagram :size="14" /> {{ store.instagram }}</a>

                <!-- Description -->
                <div class="mt-8">
                    <h2 class="section-title mb-3">About this item</h2>
                    <div class="whitespace-pre-line text-[15px] leading-relaxed text-ink-700">{{ product.description }}</div>
                    <p v-if="product.title_ai_generated" class="mt-4 inline-flex items-center gap-1.5 text-xs text-ink-400"><Sparkles :size="12" /> Title suggested by neaby AI from the seller's description</p>
                </div>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    <div class="surface flex items-center gap-3 p-4">
                        <IconCircle color="#16b26a" :size="40"><Package :size="18" /></IconCircle>
                        <div class="text-sm"><div class="font-semibold">Local seller</div><div class="text-ink-500">Arrange delivery</div></div>
                    </div>
                    <div class="surface flex items-center gap-3 p-4">
                        <IconCircle color="#4f46e5" :size="40"><BadgeCheck :size="18" /></IconCircle>
                        <div class="text-sm"><div class="font-semibold">Reviewed</div><div class="text-ink-500">Moderated listing</div></div>
                    </div>
                </div>
            </section>
        </div>

        <section v-if="moreFromStore.length" class="mt-12">
            <h2 class="section-title mb-4">More from {{ store?.name }}</h2>
            <ProductGrid :products="moreFromStore" :show-store="false" dense />
        </section>
        <section v-if="similar.length" class="mt-12">
            <h2 class="section-title mb-4">You might also like</h2>
            <ProductGrid :products="similar" dense />
        </section>
        <ShareSheet :show="sharing" kind="product" :id="product.id" :url="shareUrl || product.url" :title="product.title" :text="`${product.title} — ${product.price} on neaby`" :card-base="route('share.product', product.slug)" :qr-url="route('qr.product', product.slug)" @close="sharing = false" />
    </AppShell>
</template>
