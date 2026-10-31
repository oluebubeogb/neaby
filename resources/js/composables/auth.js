import { router } from '@inertiajs/vue3';

/** Sign-in URL that brings the visitor back to where they are now. */
export function loginUrl(name = 'login', back = null) {
    const here = back || (typeof window !== 'undefined' ? window.location.pathname + window.location.search : '/');
    return route(name, { redirect: here });
}

/** Send a guest to sign in, then back to this page. */
export function requireLogin(back = null) {
    router.visit(loginUrl('login', back));
}
