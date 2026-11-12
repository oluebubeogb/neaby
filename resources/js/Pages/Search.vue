<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Search, SlidersHorizontal, X, PackageSearch, ScanLine, Mic, Gift, Wallet, Sparkles, Loader2, Camera } from 'lucide-vue-next';
import PhotoSearchButton from '@/Components/PhotoSearchButton.vue';
import TripPicker from '@/Components/TripPicker.vue';
import { openVoice } from '@/composables/assistant';
import { postJson } from '@/composables/http';
import QrScanner from '@/Components/QrScanner.vue';
import Tip from '@/Components/Tip.vue';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppIcon from '@/Components/AppIcon.vue';
import IconCircle from '@/Components/IconCircle.vue';
import ProductGrid from '@/Components/ProductGrid.vue';
import StoreChip from '@/Components/StoreChip.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pager from '@/Components/Pager.vue';
import NearMe from '@/Components/NearMe.vue';

const props = defineProps({ filters: Object, results: Object, stores: Array, states: Array, nearby: Boolean, seo: Object, understood: Object, wallet: Object });

const f = reactive({ ...props.filters });
const showFilters = ref(false);
const scanning = ref(false);
const sorts = { relevance: 'Best match', newest: 'Newest', price_asc: 'Price: low to high', price_desc: 'Price: high to low' };
const activeCount = computed(() => ['category', 'min', 'max', 'condition', 'state'].filter((k) => props.filters[k]).length + (props.filters.in_stock ? 1 : 0));

// Plain-language understanding runs on SUBMIT only (ai=1; the server caches the parse
// per query, so paging/sorting never calls the model again). "Search exactly" turns it off.
const useAi = ref(!(new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('ai') === '0'));
function apply(extra = {}) {
    const params = Object.fromEntries(
        Object.entries({ ...f, ...extra }).filter(([k, v]) => v !== null && v !== '' && v !== false && !(k === 'sort' && v === 'relevance')),
    );
    if (params.in_stock) params.in_stock = 1;
    if (params.under_balance) params.under_balance = 1;
    if (params.q && page.props.features?.shopperAi) params.ai = useAi.value ? 1 : 0;
    panel.value = null;
    showFilters.value = false;
    router.get(route('search'), params, { preserveState: true, preserveScroll: true });
}
function clearAll() {
    Object.assign(f, { category: null, min: null, max: null, condition: null, state: null, in_stock: false, under_balance: false, sort: 'relevance' });
    apply();
}
function searchExactly() {
    useAi.value = false;
    apply();
}
function toggleBalance() {
    f.under_balance = !f.under_balance;
    apply();
}

// Photo match / "What can I get?" results (JSON, shown in place of the normal grid).
const page = usePage();
const panel = ref(null); // { kind, title, preview?, items, message, loading }
function photoStart({ preview }) { panel.value = { kind: 'photo', title: 'Photo matches', preview, items: [], loading: true, message: null }; }
function photoDone(res) {
    panel.value = { kind: 'photo', title: 'Photo matches', preview: res.preview, items: res.items || [], loading: false, message: res.ok === false || !res.items?.length ? res.message : null };
}
function photoFail(e) { panel.value = { kind: 'photo', title: 'Photo matches', preview: e.preview, items: [], loading: false, message: e.message }; }
async function whatCanIGet() {
    panel.value = { kind: 'budget', title: `What you can get with ${props.wallet?.available}`, items: [], loading: true, message: null };
    try {
        const res = await postJson(route('assistant.budget'), { wish: f.q || '' });
        panel.value = { ...panel.value, items: res.items || [], loading: false, message: res.message, ideas: res.ideas };
    } catch (e) {
        panel.value = { ...panel.value, loading: false, message: e.status === 429 ? 'Give it a minute and try again.' : 'That didn’t go through. Try again.' };
    }
}
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Search" wide>
        <form class="sticky top-[calc(3.5rem+var(--safe-top))] z-20 -mx-3 mb-4 bg-ink-50/85 px-3 pb-3 pt-1 backdrop-blur-xl sm:-mx-4 sm:px-4 md:static md:mx-0 md:bg-transparent md:px-0 md:backdrop-blur-none" role="search" @submit.prevent="apply()">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <Search :size="19" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-400" />
                    <input v-model="f.q" type="search" autofocus :placeholder="$page.props.features?.shopperAi ? 'Try “black heels near me under 20k”' : 'Search products, stores, categories'" class="input rounded-full py-3 pl-11 shadow-soft" :class="$page.props.features?.photoSearch ? 'pr-[7.5rem]' : 'pr-24'" enterkeyhint="search" />
                    <div class="absolute right-1.5 top-1/2 flex -translate-y-1/2 items-center">
                        <Tip label="Shop with me (voice)" side="bottom">
                            <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-ink-500 transition hover:bg-ink-100 hover:text-brand-700" aria-label="Shop with me by voice" @click="openVoice"><Mic :size="18" /></button>
                        </Tip>
                        <PhotoSearchButton v-if="$page.props.features?.photoSearch" :under-balance="!!f.under_balance" @start="photoStart" @results="photoDone" @error="photoFail" />
                        <Tip label="Scan a QR code" side="bottom">
                            <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-ink-500 transition hover:bg-ink-100 hover:text-ink-900" aria-label="Scan a QR code" @click="scanning = true"><ScanLine :size="18" /></button>
                        </Tip>
                    </div>
                </div>
                <button type="button" class="btn-ghost relative rounded-full px-4 lg:hidden" aria-label="Filters" @click="showFilters = !showFilters">
                    <SlidersHorizontal :size="18" />
                    <span v-if="activeCount" class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-[11px] font-bold text-white">{{ activeCount }}</span>
                </button>
                <button class="btn-primary hidden rounded-full px-6 sm:inline-flex">Search</button>
            </div>
            <!-- Shopper helpers: each one only runs when tapped. -->
            <div class="no-scrollbar -mx-3 mt-2.5 flex gap-2 overflow-x-auto px-3 pb-0.5 sm:-mx-4 sm:px-4 md:mx-0 md:px-0">
                <button v-if="wallet" type="button" class="chip" :class="filters.under_balance && 'chip-active'" @click="toggleBalance">
                    <Wallet :size="15" :class="filters.under_balance ? '' : 'text-brand-600'" /> Under my balance · {{ wallet.available }}
                </button>
                <button v-if="wallet && wallet.available_minor > 0" type="button" class="chip" @click="whatCanIGet"><Sparkles :size="15" class="text-brand-600" /> What can I get?</button>
                <Link :href="route('gift')" class="chip"><Gift :size="15" class="text-rose-500" /> Gift ideas</Link>
                <TripPicker />
                <button type="button" class="chip" @click="openVoice"><Mic :size="15" class="text-brand-600" /> Shop with me</button>
            </div>
        </form>

        <div class="lg:grid lg:grid-cols-[260px_1fr] lg:gap-8">
            <!-- Filters: bottom sheet on mobile, inline panel on tablet, sidebar on desktop -->
            <div v-if="showFilters" class="fixed inset-0 z-50 bg-ink-950/40 backdrop-blur-sm md:hidden" @click="showFilters = false" />
            <aside
                class="lg:block"
                :class="showFilters
                    ? 'fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto rounded-t-[2rem] bg-white p-5 pb-safe shadow-lift md:static md:z-auto md:mb-6 md:max-h-none md:rounded-3xl md:border md:border-ink-200/70 md:shadow-soft'
                    : 'hidden'"
            >
                <div class="lg:sticky lg:top-24">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-base font-bold">Filters</h2>
                        <div class="flex items-center gap-1">
                            <button v-if="activeCount" type="button" class="text-sm font-semibold text-brand-700" @click="clearAll">Clear all</button>
                            <button type="button" class="icon-btn md:hidden" aria-label="Close" @click="showFilters = false"><X :size="18" /></button>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3 lg:grid-cols-1">
                        <div>
                            <label class="label">Category</label>
                            <select v-model="f.category" class="input py-2.5">
                                <option :value="null">All categories</option>
                                <option v-for="c in $page.props.categories" :key="c.id" :value="c.slug">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Price (₦)</label>
                            <div class="flex items-center gap-2">
                                <input v-model="f.min" type="number" min="0" inputmode="numeric" placeholder="Min" class="input py-2.5" />
                                <span class="text-ink-300">–</span>
                                <input v-model="f.max" type="number" min="0" inputmode="numeric" placeholder="Max" class="input py-2.5" />
                            </div>
                        </div>
                        <div>
                            <label class="label">Location</label>
                            <select v-model="f.state" class="input py-2.5">
                                <option :value="null">Anywhere in Nigeria</option>
                                <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Condition</label>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="c in [null, 'new', 'used', 'refurbished']" :key="c || 'any'" type="button" class="chip capitalize" :class="f.condition === c && 'chip-active'" @click="f.condition = c">{{ c || 'Any' }}</button>
                            </div>
                        </div>
                        <label class="flex items-center gap-2.5 text-sm font-medium text-ink-700">
                            <input v-model="f.in_stock" type="checkbox" class="h-5 w-5 rounded-md border-ink-300 text-brand-500 focus:ring-brand-400" /> In stock only
                        </label>
                    </div>
                    <button type="button" class="btn-primary mt-6 w-full" @click="apply()">Show results</button>
                </div>
            </aside>

            <section class="min-w-0">
                <!-- Photo match / "What can I get?" results -->
                <template v-if="panel">
                    <div class="mb-4 flex items-center gap-3">
                        <img v-if="panel.preview" :src="panel.preview" alt="Your photo" class="h-14 w-14 shrink-0 rounded-2xl object-cover ring-2 ring-brand-200" />
                        <div class="min-w-0 flex-1">
                            <h2 class="section-title">{{ panel.title }}</h2>
                            <p class="text-xs text-ink-500"><template v-if="panel.loading">Looking through stock nearby…</template><template v-else-if="panel.items.length">{{ panel.items.length }} in stock{{ $page.props.near ? ', nearest first' : '' }}</template></p>
                        </div>
                        <NearMe />
                        <button type="button" class="icon-btn" aria-label="Close" @click="panel = null"><X :size="18" /></button>
                    </div>
                    <div v-if="panel.loading" class="flex h-40 items-center justify-center"><Loader2 :size="26" class="animate-spin text-brand-500" /></div>
                    <ProductGrid v-else-if="panel.items.length" :products="panel.items" dense />
                    <EmptyState v-else :title="panel.kind === 'photo' ? 'No look-alikes yet' : 'Nothing yet'" :body="panel.message || 'Try again in a moment.'" color="#0891b2">
                        <template #icon><Camera v-if="panel.kind === 'photo'" :size="28" /><PackageSearch v-else :size="28" /></template>
                        <button class="btn-ghost" @click="panel = null">Back to search</button>
                    </EmptyState>
                </template>

                <template v-else-if="results">
                    <!-- Plain-language understanding (only when the model parsed the query) -->
                    <div v-if="understood" class="mb-4 flex flex-wrap items-center gap-2 rounded-2xl border border-brand-100 bg-brand-50/70 px-3.5 py-2.5 text-sm">
                        <Sparkles :size="15" class="shrink-0 text-brand-600" />
                        <span class="font-medium text-ink-700">Showing</span>
                        <span v-for="c in understood.chips" :key="c" class="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ c }}</span>
                        <NearMe v-if="understood.ask_location" />
                        <button type="button" class="ml-auto text-xs font-semibold text-brand-700 hover:underline" @click="searchExactly">Search exactly “{{ understood.original }}”</button>
                    </div>

                    <div v-if="stores.length" class="mb-6">
                        <h2 class="section-title mb-3">Stores</h2>
                        <div class="no-scrollbar -mx-3 flex gap-3 overflow-x-auto px-3 pb-2 sm:-mx-4 sm:px-4 md:mx-0 md:px-0">
                            <StoreChip v-for="s in stores" :key="s.id" :store="s" />
                        </div>
                    </div>

                    <div class="mb-4 flex items-center justify-between gap-3">
                        <p class="text-sm text-ink-500"><span class="font-semibold text-ink-900">{{ results.total }}</span> {{ results.total === 1 ? 'result' : 'results' }}<template v-if="filters.q"> for “{{ filters.q }}”</template></p>
                        <NearMe class="ml-auto" />
                        <select v-model="f.sort" class="rounded-full border-ink-200 bg-white py-2 pl-3 pr-8 text-sm font-medium focus:border-brand-400 focus:ring-brand-100" @change="apply()">
                            <option v-for="(label, key) in sorts" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>

                    <ProductGrid v-if="results.data.length" :products="results.data" dense />
                    <EmptyState v-else title="No matches yet" body="Try fewer words, a different spelling, or clear some filters." color="#0891b2">
                        <template #icon><PackageSearch :size="28" /></template>
                        <button class="btn-ghost" @click="clearAll">Clear filters</button>
                    </EmptyState>
                    <Pager :prev="results.prev_page_url" :next="results.next_page_url" :label="`Page ${results.current_page} of ${results.last_page}`" />
                </template>

                <template v-else>
                    <h2 class="section-title mb-4">Browse categories</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        <Link
                            v-for="c in $page.props.categories"
                            :key="c.id"
                            :href="route('categories.show', c.slug)"
                            class="surface group flex items-center gap-3 p-3.5 transition hover:-translate-y-0.5 hover:shadow-lift"
                        >
                            <IconCircle :color="c.accent" :size="44"><AppIcon :name="c.icon" :size="20" /></IconCircle>
                            <span class="text-sm font-semibold leading-tight text-ink-800">{{ c.name }}</span>
                        </Link>
                    </div>
                </template>
            </section>
        </div>
        <QrScanner :show="scanning" @close="scanning = false" />
    </AppShell>
</template>
