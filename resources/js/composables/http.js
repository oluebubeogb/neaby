/** Tiny same-origin JSON helpers (XSRF cookie → header), used by chat + calls. */
export function xsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

async function handle(res) {
    let data = null;
    try { data = await res.json(); } catch (_) { /* empty */ }
    if (!res.ok) {
        const msg = data?.errors ? Object.values(data.errors).flat()[0] : data?.message || `Request failed (${res.status})`;
        const err = new Error(msg);
        err.status = res.status;
        err.data = data;
        throw err;
    }
    return data;
}

const base = () => ({ Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' });

export async function getJson(url, opts = {}) {
    return handle(await fetch(url, { credentials: 'same-origin', headers: base(), ...opts }));
}

export async function postJson(url, body = {}) {
    const isForm = body instanceof FormData;
    return handle(await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: isForm ? base() : { ...base(), 'Content-Type': 'application/json' },
        body: isForm ? body : JSON.stringify(body),
    }));
}
