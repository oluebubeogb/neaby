/**
 * One-tap "Add to cart" for product cards and AI suggestions. Same rules as the
 * immersive viewer: guests sign in first, listings with sizes/colours open the
 * product page to choose, otherwise POST /cart/{id} (cart.add) with just-added
 * feedback + the fly-to-cart animation. Only the cart counts reload afterwards,
 * so search / photo / gift results never re-run (and never re-call any AI).
 */
import { nextTick, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { requireLogin } from '@/composables/auth';
import { cartAdded, flyToCart } from '@/composables/cartFx';

export const quick = reactive({ adding: {}, added: {}, error: {} });

export function useQuickAdd() {
    const page = usePage();
    const inCart = (p) => Number(page.props.counts?.cart_items?.[p.id] || 0);
    const canQuickAdd = (p) => !!p && p.can_buy !== false && p.in_stock !== false && !p.mine && !!page.props.features?.checkout;

    function add(p, e) {
        if (!page.props.auth?.user) return requireLogin();
        if (p.has_options) return router.visit(route('products.show', p.slug));
        if (quick.adding[p.id]) return;
        const btn = e?.currentTarget;
        quick.adding[p.id] = true;
        quick.error[p.id] = null;
        router.post(route('cart.add', p.id), { quantity: 1 }, {
            preserveScroll: true,
            preserveState: true,
            only: ['counts', 'flash'],
            onSuccess: () => {
                nextTick(() => flyToCart(btn, p.image));
                cartAdded(1);
                quick.added[p.id] = true;
                setTimeout(() => (quick.added[p.id] = false), 1600);
            },
            onError: (errs) => {
                quick.error[p.id] = errs.quantity || errs.product || errs.options || "Couldn't add to cart.";
                setTimeout(() => (quick.error[p.id] = null), 3200);
            },
            onFinish: () => (quick.adding[p.id] = false),
        });
    }

    return { add, inCart, canQuickAdd, quick };
}
