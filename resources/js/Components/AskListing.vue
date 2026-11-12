<script setup>
/**
 * "Ask about this item": answered from THIS listing and its shop only, and only
 * when the shopper submits. Unknown → it says so and points to the seller.
 */
import { ref } from 'vue';
import { Loader2, MessageCircleQuestionMark, Send } from 'lucide-vue-next';
import { postJson } from '@/composables/http';

const props = defineProps({ slug: { type: String, required: true } });
const question = ref('');
const busy = ref(false);
const answers = ref([]); // { q, a, ok }
const quick = ['Is it true to size?', 'What colours are there?', 'Can I pick it up?', 'Is it original?'];

async function ask(q = null) {
    const text = (q ?? question.value).trim();
    if (text.length < 3 || busy.value) return;
    busy.value = true;
    try {
        const res = await postJson(route('products.ask', props.slug), { question: text });
        answers.value.unshift({ q: text, a: res.answer || res.message, ok: res.ok });
        question.value = '';
    } catch (e) {
        answers.value.unshift({ q: text, a: e.status === 429 ? 'Too many questions — try again in a minute.' : 'That didn’t go through. Try again, or message the seller.', ok: false });
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="surface mt-6 p-4">
        <h2 class="flex items-center gap-2 font-display text-base font-bold text-ink-900"><MessageCircleQuestionMark :size="18" class="text-brand-600" /> Ask about this item</h2>
        <p class="mt-0.5 text-xs text-ink-500">Answered from this listing and shop only. Not sure? Message the seller.</p>
        <form class="mt-3 flex gap-2" @submit.prevent="ask()">
            <input v-model="question" type="text" maxlength="300" class="input flex-1 rounded-full py-2.5" placeholder="e.g. Does it come with a charger?" enterkeyhint="send" />
            <button class="icon-btn-primary rounded-full" :disabled="busy || question.trim().length < 3" aria-label="Ask">
                <Loader2 v-if="busy" :size="18" class="animate-spin" /><Send v-else :size="18" />
            </button>
        </form>
        <div v-if="!answers.length" class="no-scrollbar mt-3 flex gap-2 overflow-x-auto">
            <button v-for="qq in quick" :key="qq" type="button" class="chip py-1.5 text-xs" :disabled="busy" @click="ask(qq)">{{ qq }}</button>
        </div>
        <div v-for="(x, i) in answers" :key="i" class="mt-3 text-sm">
            <p class="font-semibold text-ink-800">{{ x.q }}</p>
            <p class="mt-1 rounded-2xl px-3 py-2 leading-relaxed" :class="x.ok ? 'bg-brand-50 text-ink-800' : 'bg-ink-50 text-ink-600'">{{ x.a }}</p>
        </div>
    </div>
</template>
