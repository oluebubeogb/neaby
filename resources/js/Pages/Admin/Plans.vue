<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import SeoHead from '@/Components/SeoHead.vue';

const props = defineProps({ plans: Array, keys: Array, grants: Array, seo: Object });
const edits = reactive(Object.fromEntries(props.plans.map((p) => [p.id, { price: p.price_minor / 100, is_active: p.is_active }])));
const savePlan = (p) => router.post(route('admin.plans.update', p.id), { price: edits[p.id].price, is_active: edits[p.id].is_active ? 1 : 0 }, { preserveScroll: true });
const grant = useForm({ username: '', key: props.keys[0], days: 30, reason: '' });
const doGrant = () => grant.post(route('admin.plans.grant'), { preserveScroll: true, onSuccess: () => grant.reset('username', 'reason') });
</script>

<template>
    <SeoHead :seo="seo" />
    <AppShell title="Plans" :back="route('admin.index')" wide>
        <div class="surface overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="text-left text-xs text-ink-500"><tr><th class="p-3">Plan</th><th class="p-3">Type</th><th class="p-3">Price ₦</th><th class="p-3">Active</th><th class="p-3">Subscribers</th><th></th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="p in plans" :key="p.id">
                        <td class="p-3"><div class="font-medium">{{ p.name }}</div><div class="text-xs text-ink-400">{{ p.code }} · {{ p.duration_days }} days</div></td>
                        <td class="p-3 capitalize">{{ p.type.replace('_', ' ') }}</td>
                        <td class="p-3"><input v-model.number="edits[p.id].price" type="number" min="0" class="input w-32 py-1.5" /></td>
                        <td class="p-3"><input v-model="edits[p.id].is_active" type="checkbox" class="h-5 w-5 rounded text-brand-600" /></td>
                        <td class="p-3">{{ p.subscribers }}</td>
                        <td class="p-3"><button class="btn-dark btn-sm" @click="savePlan(p)">Save</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="hint mt-2">Price changes apply to new purchases and renewals; current periods are kept.</p>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <form class="surface grid gap-3 p-5" @submit.prevent="doGrant">
                <h2 class="section-title">Grant entitlement</h2>
                <input v-model="grant.username" class="input" placeholder="@username" />
                <div class="grid grid-cols-2 gap-3"><select v-model="grant.key" class="input"><option v-for="k in keys" :key="k" :value="k">{{ k }}</option></select><input v-model.number="grant.days" type="number" min="1" class="input" placeholder="Days" /></div>
                <input v-model="grant.reason" class="input" placeholder="Reason (audit log)" />
                <p v-for="(e, k) in grant.errors" :key="k" class="text-xs text-red-600">{{ e }}</p>
                <button class="btn-primary" :disabled="grant.processing">Grant</button>
            </form>
            <section class="surface p-5 text-sm">
                <h2 class="section-title mb-2">Recent grants</h2>
                <div v-for="(g, i) in grants" :key="i" class="flex justify-between border-b border-ink-100 py-1.5 last:border-0"><span>@{{ g.user }} · {{ g.key }}</span><span class="text-ink-500">{{ g.status }}<template v-if="g.expires"> · until {{ g.expires }}</template></span></div>
                <p v-if="!grants.length" class="text-ink-500">None yet.</p>
            </section>
        </div>
    </AppShell>
</template>
