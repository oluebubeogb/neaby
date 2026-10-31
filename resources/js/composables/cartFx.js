/**
 * Add-to-cart feedback: a thumbnail "flies" from the button into the nearest
 * visible cart target ([data-cart-target]) and the target bumps. Pure Web
 * Animations API — no deps; skipped for prefers-reduced-motion.
 */
import { reactive } from 'vue';

export const cartPulse = reactive({ n: 0, added: 0 }); // n increments on every successful add

const reduced = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function visibleTarget(scope) {
    const root = scope || document;
    const els = [...root.querySelectorAll('[data-cart-target]')].filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight;
    });
    return els[els.length - 1] || null; // last = top-most layer (immersive is teleported last)
}

export function flyToCart(fromEl, imgSrc, scope = null) {
    if (!fromEl || reduced()) return;
    const to = visibleTarget(scope);
    if (!to) return;
    const a = fromEl.getBoundingClientRect();
    const b = to.getBoundingClientRect();
    const size = 44;
    const dot = document.createElement('div');
    dot.setAttribute('aria-hidden', 'true');
    Object.assign(dot.style, {
        position: 'fixed', left: `${a.left + a.width / 2 - size / 2}px`, top: `${a.top + a.height / 2 - size / 2}px`,
        width: `${size}px`, height: `${size}px`, borderRadius: '9999px', zIndex: 100, pointerEvents: 'none',
        background: imgSrc ? `center/cover no-repeat url("${imgSrc}")` : '#16b26a',
        boxShadow: '0 10px 30px -8px rgba(22,178,106,.65), 0 0 0 3px #fff',
    });
    document.body.appendChild(dot);
    const dx = b.left + b.width / 2 - (a.left + a.width / 2);
    const dy = b.top + b.height / 2 - (a.top + a.height / 2);
    // Arc: rise first, then dive into the cart.
    dot.animate([
        { transform: 'translate(0,0) scale(1)', opacity: 1 },
        { transform: `translate(${dx * 0.45}px, ${Math.min(dy, 0) * 0.45 - 70}px) scale(1.15)`, opacity: 1, offset: 0.45 },
        { transform: `translate(${dx}px, ${dy}px) scale(.25)`, opacity: 0.4 },
    ], { duration: 720, easing: 'cubic-bezier(.45,.05,.35,1)' }).finished.finally(() => {
        dot.remove();
        to.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.28) rotate(-8deg)' }, { transform: 'scale(.94) rotate(4deg)' }, { transform: 'scale(1)' }], { duration: 480, easing: 'ease-out' });
    });
}

export function cartAdded(qty = 1) {
    cartPulse.added = qty;
    cartPulse.n++;
    try { navigator.vibrate?.(18); } catch (_) {}
}
