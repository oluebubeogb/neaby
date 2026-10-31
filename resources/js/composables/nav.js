import { Home, Search, PlusCircle, Bell, UserRound, LayoutGrid, ShieldCheck, ShoppingBag, Heart, Package, Repeat2, Crown } from 'lucide-vue-next';

/** Primary destinations — the same five everywhere, presented per breakpoint. */
export function primaryNav(user, counts = {}) {
    return [
        { key: 'home', label: 'Home', icon: Home, href: route('home'), match: ['home'] },
        { key: 'search', label: 'Search', icon: Search, href: route('search'), match: ['search', 'categories.*'] },
        { key: 'sell', label: 'Sell', icon: PlusCircle, href: route('sell.dashboard'), match: ['sell.*'], primary: true },
        { key: 'inbox', label: 'Inbox', icon: Bell, href: route('inbox'), match: ['inbox'], badge: counts?.unread || 0 },
        {
            key: 'profile',
            label: user ? 'Profile' : 'Sign in',
            icon: UserRound,
            href: user ? route('profile.show') : route('login'),
            match: ['profile.*', 'login', 'register'],
        },
    ];
}

export function secondaryNav(user, counts = {}, features = {}, entitlements = []) {
    const items = [{ key: 'categories', label: 'Categories', icon: LayoutGrid, href: route('categories.index'), match: ['categories.*'] }];
    if (user) {
        if (features?.checkout !== false) items.push({ key: 'cart', label: 'Cart', icon: ShoppingBag, href: route('cart.index'), match: ['cart.*', 'checkout.*'], badge: counts?.cart || 0 });
        items.push({ key: 'orders', label: 'Orders', icon: Package, href: route('orders.index'), match: ['orders.*'] });
        items.push({ key: 'saved', label: 'Saved', icon: Heart, href: route('saved.index'), match: ['saved.*'] });
        items.push({ key: 'resell', label: 'Resell', icon: Repeat2, href: route('resell.index'), match: ['resell.*', 'requests.*'] });
        items.push({ key: 'membership', label: 'Membership', icon: Crown, href: route('membership.index'), match: ['membership.*'] });
    }
    if (user?.is_admin) items.push({ key: 'admin', label: 'Admin', icon: ShieldCheck, href: route('admin.index'), match: ['admin.*'] });
    return items;
}

export function isActive(item) {
    return item.match.some((m) => route().current(m));
}
