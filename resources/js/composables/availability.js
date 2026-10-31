import { ref, watch } from 'vue';
import { getJson } from '@/composables/http';

/**
 * Debounced server-side availability check for a username or email.
 * Returns { state: 'idle'|'checking'|'ok'|'taken', message }.
 * `unchanged` is the value the user already owns (skips the check).
 */
export function useAvailability(field, source, unchanged = null, delay = 450) {
    const state = ref('idle');
    const message = ref('');
    let timer = null;
    let seq = 0;

    watch(source, (value) => {
        clearTimeout(timer);
        const v = String(value || '').trim();
        if (!v || (unchanged && v.toLowerCase() === String(unchanged).toLowerCase())) {
            state.value = 'idle';
            message.value = '';
            return;
        }
        if (field === 'username' && v.length < 3) { state.value = 'idle'; message.value = ''; return; }
        if (field === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { state.value = 'idle'; message.value = ''; return; }
        state.value = 'checking';
        message.value = '';
        const mine = ++seq;
        timer = setTimeout(async () => {
            try {
                const r = await getJson(route('profile.availability', { field, value: v }));
                if (mine !== seq) return;
                state.value = r.ok ? 'ok' : 'taken';
                message.value = r.message || '';
            } catch (_) {
                if (mine === seq) { state.value = 'idle'; message.value = ''; }
            }
        }, delay);
    });

    return { state, message };
}
