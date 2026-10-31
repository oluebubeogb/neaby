<script setup>
import { Link } from '@inertiajs/vue3';
import { ImageOff, MapPin } from 'lucide-vue-next';

defineProps({
    product: { type: Object, required: true },
    showStore: { type: Boolean, default: true },
});
</script>

<template>
    <Link
        :href="product.sp ? route('products.show', { product: product.slug, sp: product.sp }) : route('products.show', product.slug)"
        class="group flex flex-col overflow-hidden rounded-3xl border border-ink-200/60 bg-white shadow-soft transition duration-200 hover:-translate-y-0.5 hover:shadow-lift"
    >
        <div class="relative aspect-square overflow-hidden bg-ink-100">
            <img
                v-if="product.image"
                :src="product.image"
                :alt="product.title"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
            />
            <div v-else class="flex h-full items-center justify-center text-ink-300"><ImageOff :size="28" /></div>

            <div class="absolute left-2 top-2 flex gap-1">
                <span v-if="product.is_new" class="badge bg-white/90 text-brand-700 backdrop-blur">New</span>
                <span v-if="product.condition === 'used'" class="badge bg-ink-900/70 text-white backdrop-blur">Used</span>
                <span v-if="product.condition === 'refurbished'" class="badge bg-ink-900/70 text-white backdrop-blur">Refurb</span>
            </div>
            <span v-if="!product.in_stock" class="badge absolute bottom-2 left-2 bg-red-600 text-white">Sold out</span>
            <span v-if="product.sponsored" class="badge absolute right-2 top-2 bg-amber-100/95 text-amber-900 backdrop-blur" title="Paid placement">Sponsored</span>
        </div>

        <div class="flex flex-1 flex-col gap-1 p-3 sm:p-3.5">
            <h3 class="line-clamp-2 text-[13.5px] font-medium leading-snug text-ink-800 sm:text-sm">{{ product.title }}</h3>
            <div class="mt-auto flex items-baseline gap-1.5 pt-1">
                <span class="font-display text-[15px] font-bold text-ink-900 sm:text-base">{{ product.price }}</span>
                <span v-if="product.compare_at" class="text-xs text-ink-400 line-through">{{ product.compare_at }}</span>
            </div>
            <div v-if="showStore && product.store" class="flex items-center gap-1.5 text-xs text-ink-500">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ background: product.store.accent || '#16b26a' }" />
                <span class="truncate">{{ product.store.name }}</span>
                <template v-if="product.city">
                    <span class="text-ink-300">·</span>
                    <MapPin :size="11" class="shrink-0" /><span class="truncate">{{ product.city }}</span>
                </template>
            </div>
        </div>
    </Link>
</template>
