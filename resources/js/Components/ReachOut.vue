<script setup>
/**
 * The one "reach out" affordance for product, store and profile pages.
 * Opens a compact menu: in-app chat (default), voice call, and — if the
 * seller shared them — WhatsApp / Instagram.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Instagram, MessageCircle, Phone } from 'lucide-vue-next';
import WhatsAppLine from '@/Components/icons/WhatsAppLine.vue';
import Tip from '@/Components/Tip.vue';
import { requireLogin } from '@/composables/auth';

const props = defineProps({
    product: { type: Number, default: null },
    store: { type: Number, default: null },
    user: { type: String, default: null },
    whatsapp: { type: String, default: null }, // full wa.me link
    instagram: { type: String, default: null }, // handle
    draft: { type: String, default: '' },
    name: { type: String, default: 'seller' },
    variant: { type: String, default: 'icon' }, // icon | button | immersive
    align: { type: String, default: 'right' }, // menu alignment
    up: { type: Boolean, default: false },
});

const page = usePage();
const rtc = computed(() => page.props.rtc || {});
const open = ref(false);
const root = ref(null);
const busy = ref(false);

function start(call = false) {
    open.value = false;
    if (!page.props.auth?.user) return requireLogin();
    busy.value = true;
    router.post(route('messages.start'), { product: props.product, store: props.store, user: props.user, draft: props.draft || null, call: call ? 1 : 0 }, { onFinish: () => (busy.value = false) });
}
const ig = computed(() => (props.instagram ? `https://instagram.com/${props.instagram.replace('@', '')}` : null));

function onDoc(e) { if (open.value && root.value && !root.value.contains(e.target)) open.value = false; }
function onKey(e) { if (e.key === 'Escape') open.value = false; }
onMounted(() => { document.addEventListener('click', onDoc); document.addEventListener('keydown', onKey); });
onBeforeUnmount(() => { document.removeEventListener('click', onDoc); document.removeEventListener('keydown', onKey); });

const btnClass = computed(() => ({
    icon: 'icon-btn-lg',
    button: 'btn-primary btn-lg',
    immersive: 'immersive-btn',
}[props.variant]));
</script>

<template>
    <div ref="root" class="relative inline-flex">
        <Tip v-if="variant !== 'button'" :label="`Message ${name}`" :side="variant === 'immersive' ? 'left' : 'top'">
            <button type="button" :class="btnClass" :disabled="busy" :aria-expanded="open" aria-haspopup="menu" :aria-label="`Message ${name}`" @click.stop="open = !open">
                <MessageCircle :size="variant === 'immersive' ? 22 : 19" />
            </button>
        </Tip>
        <button v-else type="button" :class="btnClass" :disabled="busy" :aria-expanded="open" aria-haspopup="menu" @click.stop="open = !open">
            <MessageCircle :size="18" /> Message
        </button>

        <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" leave-active-class="transition duration-100" leave-to-class="opacity-0 scale-95">
            <div
                v-if="open"
                role="menu"
                class="absolute z-50 w-60 overflow-hidden rounded-2xl border border-white/60 bg-white/85 p-1.5 text-sm shadow-lift backdrop-blur-xl"
                :class="[align === 'right' ? 'right-0' : 'left-0', up ? 'bottom-[calc(100%+8px)]' : 'top-[calc(100%+8px)]', variant === 'immersive' ? 'right-[calc(100%+10px)] top-auto bottom-0' : '']"
            >
                <button role="menuitem" class="menu-item" @click="start(false)"><MessageCircle :size="16" class="text-brand-600" /> Chat on neaby</button>
                <button v-if="!page.props.auth?.user || rtc.calls" role="menuitem" class="menu-item" @click="start(true)"><Phone :size="16" class="text-brand-600" /> Voice call <span class="ml-auto text-[10px] font-bold uppercase tracking-wide text-ink-400">in-app</span></button>
                <a v-if="whatsapp" role="menuitem" class="menu-item" :href="whatsapp" target="_blank" rel="noopener" @click="open = false"><WhatsAppLine :size="16" class="text-[#1fa855]" /> WhatsApp</a>
                <a v-if="ig" role="menuitem" class="menu-item" :href="ig" target="_blank" rel="noopener" @click="open = false"><Instagram :size="16" class="text-pink-600" /> Instagram</a>
            </div>
        </Transition>
    </div>
</template>
