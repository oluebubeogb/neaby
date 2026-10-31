import { ref, watch } from 'vue';

const KEY = 'neaby.sidebar.collapsed';
const collapsed = ref(false);
let loaded = false;

/** Desktop sidebar state (248px ↔ 76px), persisted per device. */
export function useSidebar() {
    if (!loaded && typeof window !== 'undefined') {
        loaded = true;
        collapsed.value = window.localStorage.getItem(KEY) === '1';
        watch(collapsed, (v) => window.localStorage.setItem(KEY, v ? '1' : '0'));
    }
    return { collapsed, toggle: () => (collapsed.value = !collapsed.value) };
}
