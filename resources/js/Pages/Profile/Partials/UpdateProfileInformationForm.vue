<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Camera, Check, Loader2 } from 'lucide-vue-next';
import { useAvailability } from '@/composables/availability';
import Avatar from '@/Components/Avatar.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ mustVerifyEmail: Boolean, status: String, states: Array });
const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    username: user.username,
    email: user.email,
    bio: user.bio ?? '',
    city: user.city ?? '',
    state: user.state ?? '',
    avatar: null,
    remove_avatar: false,
});
const preview = ref(user.avatar);
const usernameCheck = useAvailability('username', () => form.username, user.username);
const emailCheck = useAvailability('email', () => form.email, user.email);

function pick(e) {
    const f = e.target.files?.[0];
    if (!f) return;
    form.avatar = f;
    form.remove_avatar = false;
    preview.value = URL.createObjectURL(f);
}
function removeAvatar() {
    form.avatar = null;
    form.remove_avatar = true;
    preview.value = null;
}
const submit = () =>
    form.transform((d) => ({ ...d, remove_avatar: d.remove_avatar ? 1 : 0 })).post(route('profile.update'), { forceFormData: true, preserveScroll: true });
</script>

<template>
    <section>
        <h2 class="section-title">Profile</h2>
        <p class="mt-1 text-sm text-ink-500">How you appear to buyers and sellers on neaby.</p>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <Avatar :src="preview" :initials="user.initials" :size="76" />
                    <label class="absolute -bottom-1 -right-1 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-ink-900 text-white shadow-lift" aria-label="Upload photo"><Camera :size="15" /><input type="file" accept="image/*" class="hidden" @change="pick" /></label>
                </div>
                <div class="text-sm">
                    <div class="font-semibold">Profile photo</div>
                    <button v-if="preview" type="button" class="font-semibold text-red-600" @click="removeAvatar">Remove</button>
                    <span v-else class="text-ink-500">JPG, PNG or WebP</span>
                    <InputError :message="form.errors.avatar" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Full name</label>
                    <input id="name" v-model="form.name" class="input" required autocomplete="name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <label class="label" for="username">Username</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-400">@</span>
                        <input id="username" v-model="form.username" class="input pl-9" required autocomplete="username" :aria-invalid="usernameCheck.state.value === 'taken'" :class="usernameCheck.state.value === 'taken' && '!border-red-400'" @input="form.username = form.username.toLowerCase().replace(/[^a-z0-9_]/g, '')" />
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                            <Loader2 v-if="usernameCheck.state.value === 'checking'" :size="16" class="animate-spin text-ink-400" />
                            <Check v-else-if="usernameCheck.state.value === 'ok'" :size="16" class="text-brand-600" />
                        </span>
                    </div>
                    <InputError :message="form.errors.username || (usernameCheck.state.value === 'taken' ? usernameCheck.message.value : '')" />
                    <p v-if="!form.errors.username && usernameCheck.state.value === 'ok'" class="mt-1 text-xs font-medium text-brand-700">{{ usernameCheck.message.value }}</p>
                </div>
            </div>

            <div>
                <label class="label" for="email">Email</label>
                <div class="relative">
                    <input id="email" v-model="form.email" type="email" class="input pr-10" required autocomplete="email" :aria-invalid="emailCheck.state.value === 'taken'" :class="emailCheck.state.value === 'taken' && '!border-red-400'" />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                        <Loader2 v-if="emailCheck.state.value === 'checking'" :size="16" class="animate-spin text-ink-400" />
                        <Check v-else-if="emailCheck.state.value === 'ok'" :size="16" class="text-brand-600" />
                    </span>
                </div>
                <InputError :message="form.errors.email || (emailCheck.state.value === 'taken' ? emailCheck.message.value : '')" />
                <p v-if="!form.errors.email && emailCheck.state.value === 'ok' && form.email !== user.email" class="mt-1 text-xs text-ink-500">Changing your email means verifying the new address.</p>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-2xl bg-amber-50 p-3 text-sm text-amber-900">
                Your email address is unverified.
                <Link :href="route('verification.send')" method="post" as="button" class="font-semibold underline">Resend verification email</Link>
                <div v-show="status === 'verification-link-sent'" class="mt-1 font-medium text-brand-700">A new verification link has been sent.</div>
            </div>

            <div>
                <label class="label" for="bio">Bio <span class="font-normal text-ink-400">(optional)</span></label>
                <textarea id="bio" v-model="form.bio" rows="3" maxlength="280" class="input" placeholder="A line about you" />
                <InputError :message="form.errors.bio" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="city">City</label>
                    <input id="city" v-model="form.city" class="input" autocomplete="address-level2" />
                    <InputError :message="form.errors.city" />
                </div>
                <div>
                    <label class="label" for="state">State</label>
                    <select id="state" v-model="form.state" class="input">
                        <option value="">Select state</option>
                        <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <InputError :message="form.errors.state" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button class="btn-primary" :disabled="form.processing || usernameCheck.state.value === 'taken' || emailCheck.state.value === 'taken'">Save profile</button>
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm font-medium text-brand-700">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
