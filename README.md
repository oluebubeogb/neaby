# neaby

**Shop the stores near you.** A local-commerce marketplace for Nigerian independent sellers:
open a free store, list products (with AI-suggested titles), get found through search,
categories and a personalised For You feed. Buyers chat sellers on WhatsApp **or** pay on neaby
(cart → checkout → order tracking). Resellers sell supplier stock without holding it.

> Phases 1–7 of [`BLUEPRINT.md`](BLUEPRINT.md): foundation, sharing, memberships, checkout,
> seller tools, resale + Supplier Connect, growth.

| Layer | Choice |
|---|---|
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | Inertia 2 + Vue 3, **plain JavaScript (no TypeScript)**, Tailwind 3, Vite |
| Data | PostgreSQL 15+ (full-text search + `pg_trgm`), Redis (cache + queue) |
| Workers | Queue worker + scheduler run as s6 services inside the same image (`docker/s6/`) |
| Uploads | VPS disk via `App\Contracts\ObjectStore` (`LocalObjectStore` now, S3 later) |
| Payments | `App\Contracts\PaymentProvider`: `FakePaymentProvider` (default) + `MonnifyPaymentProvider` |
| AI titles | `App\Contracts\ListingAi`: `FakeListingAi` (offline, no key) / OpenAI-compatible / null |
| Fonts | Self-hosted Inter + Plus Jakarta Sans; **Comfortaa 700** for the lowercase `neaby` wordmark (`public/fonts/comfortaa-700.woff2`, `resources/fonts/Comfortaa-Bold.ttf`, SIL OFL 1.1) — used on the web (`font-brand`), in every mail template and in generated share-card PNGs |

> **Why Laravel 13, not 11?** Every Laravel 11.x release is now blocked by Packagist
> security advisories, so `composer install` refuses them. Laravel 13 is the current
> supported line; the code uses the same structure (`bootstrap/app.php`, no Kernel classes).
> Symfony 8 components need **PHP ≥ 8.4.1**.

---

## Project structure

```
app/
  Contracts/            ObjectStore, PaymentProvider, ListingAi  (swap-able drivers)
  Services/
    Storage/            LocalObjectStore (public disk on the VPS)
    Payments/           PaymentManager, FakePaymentProvider, MonnifyPaymentProvider (skeleton)
    Ai/                 FakeListingAi, OpenAiCompatibleListingAi, NullListingAi
    Search/             CatalogueSearch  (websearch_to_tsquery + ILIKE + trigram)
    Feed/               ForYouFeed       (category affinity + freshness + popularity)
  Http/Controllers/     Landing, Feed(/home), Search, Category, Product, Storefront,
                        PublicProfile, Inbox, Sitemap, Health, PaymentWebhook,
                        Sell/* (dashboard, store, products, AI), Admin/* (moderation)
  Models/               User, Store, Product, ProductImage, Category, ProductView, AuditLog, PaymentTransaction, PaymentWebhookEvent
  Support/              Present (Inertia DTOs), Seo, Admin, Username, NigeriaStates
config/neaby.php        admins, moderation, uploads, AI    config/payments.php  providers
database/migrations     users/stores/products/images/categories + tsvector/GIN/trigram indexes
database/seeders        CategorySeeder (16 categories, idempotent), NeabyDemoSeeder (optional demo cast — see “Demo data”)
resources/js/
  Layouts/AppShell.vue  responsive shell: bottom nav (mobile) · 76px rail (tablet) ·
                        248px ⇄ 76px collapsible sidebar (desktop)
  Layouts/AuthLayout.vue
  Components/           Wordmark, IconCircle, ProductCard/Grid, StoreChip, PasswordInput (eye toggle), SeoHead, …
  Pages/                Landing, Home, Search, Categories/*, Products/Show, Stores/Show, Users/Show,
                        Sell/{Start,Dashboard,StoreForm,ProductForm}, Inbox, Profile/*, Admin/*, Auth/*
resources/views/app.blade.php   server-rendered <title>/meta/OG/JSON-LD for every page
public/                 favicon.svg/.ico, apple-touch-icon, icon-192/512, site.webmanifest, og-default.png
Dockerfile              app image (serversideup/php 8.4 fpm-nginx, port 3123)
docker-entrypoint.sh    boot hook: wait for DB → migrate --force → seed categories → storage:link → optimize
docker-compose.yml      LOCAL DEV ONLY: postgres:17 + redis:7
```

### Key routes

| Path | What |
|---|---|
| `/` | Landing (signed-in users are redirected to `/home`) |
| `/home` | For You feed (infinite scroll, category chips) |
| `/search?q=` | Full-text + ILIKE search, filters (category, condition, price, state), sort |
| `/categories`, `/c/{slug}` | Category index / listing |
| `/p/{slug}` | Public SEO product page (JSON-LD `Product`) |
| `/s/{slug}` | Public store page |
| `/u/{username}` | Public profile |
| `/sell`, `/sell/store`, `/sell/products/create` | Seller hub, store setup, listing editor |
| `/admin`, `/admin/products`, `/admin/categories` | Moderation (admins only) |
| `/sitemap.xml`, `/robots.txt` | SEO |
| `/up` | Liveness (Laravel built-in, no DB) |
| `/api/health` | Deep check: DB + Redis (+ storage writable). 200 ok / 503 degraded |
| `POST /webhooks/payments/{provider}` | Payment webhooks (signature-verified, idempotent; `monnify`) |

**Phases 2–7**

| Path | What |
|---|---|
| `/share/p/{slug}.png`, `/share/s/{slug}.png`, `/share/r/{slug}.png` | Share cards (`?format=square\|portrait\|landscape&qr=1`), Comfortaa wordmark |
| `/qr/…` , `?ch=` | QR codes; `ch` tags share-channel visits for analytics |
| `/saved` | Saved products (toggle on any product) |
| `/inbox` | Notifications (orders, memberships, requests, sponsored) |
| `/cart`, `/checkout` | Cart grouped by store, delivery/pickup, stock reserved during payment |
| `/orders`, `/orders/{id}` | Buyer orders: timeline, cancel, confirm receipt, refund status |
| `/payments/fake/{ref}` | TEST MODE pay page (only when `PAYMENT_PROVIDER=fake` + `PAYMENT_FAKE_SANDBOX=true`) |
| `/membership` | Reseller / Supplier Resale / Seller Pro plans, purchase, cancel, history |
| `/resell`, `/resell/offers` | Reseller hub (supplier prices + margins) and own offers |
| `/r/{slug}` | Public reseller offer page (reseller price only, WhatsApp enquiry) |
| `/requests` | Supplier Connect requests (availability, stock hold, propose, accept, fulfil) |
| `/sell/orders` | Seller order fulfilment (processing → shipped/ready → completed, cancel/refund) |
| `/sell/import` | CSV/XLSX import (≤1000 rows) with report + errors CSV; template download |
| `/sell/bulk` | Bulk edit price, qty, category, status, checkout |
| `/sell/insights` | Views/saves/shares/orders, quality score, channels (Seller Pro: 90 days + CSV export) |
| `/sell/products/{id}/promote` | Sponsored listing (clearly labelled in For You) |
| `/sell/resale` | Supplier: open products to resellers, supplier/min price, terms, fulfilment, block resellers |
| `/admin/plans`, `/admin/analytics`, `/admin/refunds`, `/admin/sponsored` | Plan prices + grants, platform analytics, refunds, sponsored moderation |

**UX pass — messaging, calls, QR commerce**

| Path | What |
|---|---|
| `/messages`, `/messages/{id}` | 1:1 chat (text, images, voice notes) with product/store context; two-pane on desktop |
| `POST /messages/start` | Open (or reuse) a thread from a product, store or profile ("Message" control) |
| `/messages/media/{message}` | Chat images/voice — served only to the two participants |
| `POST /messages/{id}/call`, `/calls/{id}/…` | WebRTC voice call + HTTP-polled signalling (offer/answer/ICE) |
| `/q/{code}`, `/q/{code}.png`, `POST /q/{code}/add` | Seller QR code landing (discount, expiry), PNG, add to cart |
| `/sell/qr` | Seller QR codes: create (discount %/₦, preset size/qty, max uses, expiry), toggle, download, delete |
| `/availability?field=username\|email&value=` | Live availability check (sign-up and settings), throttled 60/min |
| `/profile/delete/confirm` (signed) | Account deletion — only after clicking the emailed link |

**Rules enforced server-side**
- The supplier/reseller price is only ever serialised for users with the `reseller.access` entitlement (or the owning supplier); public product JSON, Inertia props and share cards never contain it.
- Shared inventory: reseller offers point at the supplier's product stock — never copied. Orders and accepted stock holds both use `reserved_quantity` with row locks, so stock can't go negative or be double-sold.
- Two fulfilment models per request: `reseller_collects` and `supplier_dispatches`.
- Membership expiry (after `NEABY_MEMBERSHIP_GRACE_DAYS`) hides offers and locks the hub, but **accepted** supplier commitments stay valid and fulfillable.
- No reseller-of-reseller chains: offers can only be created on original supplier products.
- Rate limits: checkout 10/min, supplier requests 20/h, events 60/min, AI 12/min, webhooks 120/min.

### Moderation

Product status: `draft | pending | published | rejected` (+ `paused`, `archived` for sellers).
With `NEABY_REQUIRE_PRODUCT_REVIEW=false` (default) Publish goes straight to `published`;
set it to `true` to route new listings through `pending` → admin approval.
Admins: any username in `ADMIN_USERNAMES` (comma-separated) **or** `users.is_admin = true`.
Admins can approve, request changes (reject with reason), take down, and manage categories.
Every admin action is written to `audit_logs`.

---

## Run locally

Requirements: PHP 8.4 (`pdo_pgsql`, `intl`, `gd`, `exif`, `bcmath`, `redis` optional), Composer 2, Node 22+, Docker (for PG/Redis).

```bash
docker compose up -d                    # postgres:17 on :5432, redis:7 on :6379
cp .env.example .env
# local tweaks in .env:
#   APP_ENV=local  APP_DEBUG=true  APP_URL=http://127.0.0.1:8000  SESSION_SECURE_COOKIE=false
#   DB_HOST=127.0.0.1 DB_DATABASE=neaby DB_USERNAME=neaby DB_PASSWORD=neaby
#   ADMIN_USERNAMES=ada
composer install
php artisan key:generate
php artisan migrate --seed              # categories + plans + demo cast (non-production only)
php artisan storage:link
npm ci
npm run dev                             # or: npm run build
php artisan serve                       # http://127.0.0.1:8000
```

Demo accounts: `neaby1@beuden.com.ng` … `neaby20@beuden.com.ng` — each password is the email itself (see “Demo data”).
No Redis locally? Set `CACHE_STORE=database`; `/api/health` will report Redis as down.

---

## Deploy on Coolify

The **Dockerfile builds the app only**. PostgreSQL and Redis are **separate Coolify resources**.
`docker-compose.yml` is for local development and is *not* used by Coolify.

1. **Create the databases** (Project → *New Resource* → *Database*):
   - **PostgreSQL** (15+; 17 recommended). Note the *Postgres URL (internal)*.
   - **Redis** (7). Note the *Redis URL (internal)*.
   - Put both in the **same project/environment (network)** as the app.
2. **Create the app**: *New Resource* → *Application* → your Git repo →
   Build Pack **Dockerfile** (Dockerfile at repo root).
   - **Ports Exposes**: `3123`
   - **Health check**: path `/up`, port `3123` (use `/api/health` for uptime monitors — it also checks DB/Redis).
3. **Persistent storage** (Application → *Storages* → *Add*):
   - Volume, **Destination path `/var/www/html/storage/app/public`** — product photos, logos, avatars.
     Without this, uploads disappear on every redeploy.
   - The container runs as `www-data` (uid/gid 33). If uploads fail with permission errors on a bind mount,
     run `chown -R 33:33 <host path>` once on the server.
4. **Environment variables** (Application → *Environment Variables*): see the table below.
   Generate the key locally with `php artisan key:generate --show` and paste it as `APP_KEY`.
5. **Domain**: set your domain (e.g. `https://neaby.ng`) and the same value in `APP_URL`.
   Coolify's proxy terminates TLS; the app trusts proxy headers and forces `https` in production.
6. **Deploy.** On each boot the entrypoint waits for Postgres, runs `php artisan migrate --force`
   (all migrations are additive), seeds/refreshes default categories **and membership plans**
   (idempotent, `NEABY_SEED_CATEGORIES=true`), ensures `storage:link`, and caches config/routes/views.
   **Queue worker + scheduler** start automatically as supervised s6 services in the same container
   (`queue:work` and `schedule:work`, ~20 s after boot). They must run: the scheduler releases expired
   checkout reservations (5 min), sweeps expired memberships (hourly) and recomputes quality scores (daily);
   the worker processes imports and notification mail. Set `NEABY_RUN_WORKERS=false` only if you run them
   elsewhere (e.g. a Coolify *Scheduled Task* `php artisan schedule:run` every minute + a second app with
   command `php artisan queue:work`).
7. **First admin**: register normally, then add your username to `ADMIN_USERNAMES` and redeploy
   (or set `is_admin = true` on your user row).

The image build runs `composer install --no-dev`, `npm ci && npm run build`, `composer dump-autoload --optimize`
and `php artisan storage:link`. Uploads up to 100 MB per request are allowed by nginx/PHP
(per-image limit is `NEABY_MAX_IMAGE_KB`; the browser also downsizes large photos before upload).

### Environment variables

| Variable | Required | Example / default | Notes |
|---|---|---|---|
| `APP_NAME` | | `neaby` | |
| `APP_ENV` | ✔ | `production` | |
| `APP_KEY` | ✔ | `base64:…` | `php artisan key:generate --show`. Boot fails fast if empty. |
| `APP_DEBUG` | | `false` | Never `true` in production |
| `APP_URL` | ✔ | `https://neaby.ng` | Used for canonical URLs, sitemap, upload URLs |
| `APP_TIMEZONE` | | `Africa/Lagos` | |
| `LOG_CHANNEL` | | `stderr` | Logs appear in Coolify's log view |
| `DB_CONNECTION` | ✔ | `pgsql` | |
| `DB_URL` / `DATABASE_URL` | ✔* | `postgres://user:pass@host:5432/neaby` | Coolify internal Postgres URL. *Or* the discrete vars below |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | ✔* | | Used when no URL is set |
| `REDIS_URL` | ✔* | `redis://default:pass@host:6379/0` | Coolify internal Redis URL. *Or* the discrete vars below |
| `REDIS_HOST` `REDIS_PORT` `REDIS_PASSWORD` | ✔* | | |
| `REDIS_CLIENT` | | `phpredis` | Extension is in the image |
| `CACHE_STORE` | | `redis` | `database` works too |
| `SESSION_DRIVER` | | `database` | Survives Redis restarts |
| `SESSION_SECURE_COOKIE` | | `true` | `false` only for plain-http local dev |
| `QUEUE_CONNECTION` | | `redis` | Worker runs in-container; `sync` also works |
| `NEABY_RUN_WORKERS` | | `true` | Start in-container queue worker + scheduler |
| `FILESYSTEM_DISK` | | `local` | |
| `NEABY_UPLOAD_DISK` | | `public` | Disk behind `LocalObjectStore` (→ `storage/app/public`) |
| `NEABY_MAX_IMAGE_KB` | | `8192` | Per photo |
| `NEABY_MAX_IMAGES` | | `8` | Per listing |
| `ADMIN_USERNAMES` | | `ada,tunde` | Comma-separated admin usernames |
| `NEABY_REQUIRE_PRODUCT_REVIEW` | | `false` | `true` = new listings wait in `pending` |
| `NEABY_SEED_CATEGORIES` | | `true` | Seed default categories on boot |
| `NEABY_SEED_DEMO` | | `false` | `true` = seed the demo cast on boot (once, idempotent) · `wipe` = remove it on boot · `false` = leave data alone |
| `NEABY_DEMO_PHOTOS` | | `true` | Demo seed downloads ~10 MB of real photos; `false` = offline placeholders only |
| `LISTING_AI_DRIVER` | | `fake` | `fake` (offline) · `openai` · `null` |
| `LISTING_AI_BASE_URL` `LISTING_AI_API_KEY` `LISTING_AI_MODEL` | | | Only for `openai` (any OpenAI-compatible API) |
| `PAYMENT_PROVIDER` | | `fake` | `fake` · `monnify` (see below) |
| `PAYMENT_FAKE_SANDBOX` | | `true` | Enables the TEST MODE pay page for `fake` |
| `MONNIFY_BASE_URL` | | `https://sandbox.monnify.com` | Live: `https://api.monnify.com` |
| `MONNIFY_API_KEY` `MONNIFY_SECRET_KEY` `MONNIFY_CONTRACT_CODE` | for monnify | | From the Monnify dashboard |
| `NEABY_MAIL_NOTIFICATIONS` | | `true` | Email important notices (orders, memberships, requests) |
| `NEABY_CHECKOUT_ENABLED` | | `true` | Master switch for cart/checkout |
| `NEABY_RESERVATION_MINUTES` | | `30` | Stock held while a checkout is unpaid |
| `NEABY_MAX_QTY_PER_ITEM` | | `20` | Per cart line |
| `NEABY_MEMBERSHIP_GRACE_DAYS` | | `3` | After period end before access locks |
| `NEABY_SUPPLIER_REQUEST_EXPIRY_DAYS` | | `7` | Open requests auto-expire |
| `NEABY_MAX_RESELLER_OFFERS` | | `200` | Per reseller |
| `NEABY_SPONSORED_ENABLED` | | `true` | |
| `NEABY_SPONSORED_DAILY_PRICE_MINOR` | | `50000` | Kobo/day (₦500) |
| `NEABY_SPONSORED_EVERY` | | `8` | Max 1 sponsored card per N feed items |
| `NEABY_SELLER_PRO_ENABLED` | | `true` | Seller Pro plan (90-day insights + export) |
| `MAIL_MAILER` + `MAIL_*` | | `log` | Verify email, password reset + notices. `log` writes them to container logs. SMTP: `smtp` |
| `MAIL_SCHEME` | | `null` | Empty/`null` = auto (STARTTLS on 587). `smtps`/`ssl` for port 465. Any spelling is normalised in `config/mail.php` |
| `MAIL_HOST` `MAIL_PORT` `MAIL_USERNAME` `MAIL_PASSWORD` | for smtp | `mail.example.com` / `587` | |
| `MAIL_FROM_ADDRESS` `MAIL_FROM_NAME` | | `hello@neaby.ng` / `neaby` | Must be an address the SMTP login may send as (alias), or servers reject it |
| `MAIL_TIMEOUT` | | `15` | Seconds before an unreachable SMTP server gives up |
| `NEABY_MESSAGING_ENABLED` | | `true` | 1:1 chat on/off |
| `NEABY_CHAT_MAX_IMAGE_KB` `NEABY_CHAT_MAX_VOICE_KB` | | `1536` / `1024` | Upload caps (images are already compressed to WebP in the browser) |
| `NEABY_CHAT_MAX_VOICE_SECONDS` | | `120` | Voice-note length cap |
| `NEABY_CHAT_MAX_PER_MINUTE` | | `30` | Messages per user per minute |
| `NEABY_CHAT_POLL_SECONDS` `NEABY_CHAT_PULSE_SECONDS` | | `4` / `10` | Open-thread poll / global unread + incoming-call pulse. Paused while the tab is hidden |
| `NEABY_CALLS_ENABLED` | | `true` | WebRTC voice calls |
| `NEABY_STUN_URLS` | | Google STUN | Comma-separated |
| `NEABY_TURN_URL` `NEABY_TURN_USERNAME` `NEABY_TURN_CREDENTIAL` | prod: recommended | `turn:turn.example.com:3478?transport=udp` | TURN relay (coturn). Without it calls fail between strict/symmetric NATs |
| `NEABY_CALL_RING_SECONDS` | | `40` | Then the call is marked missed |
| `NEABY_QR_MAX_ACTIVE_PER_STORE` | | `100` | Live seller QR codes per store |
| `NEABY_QR_MAX_DISCOUNT_PERCENT` | | `90` | Cap for % and ₦ discounts on QR codes |

---

### Mail checklist

- Verify-email and password-reset mails are sent **immediately** (not queued), so they work even if the queue
  worker is down. Order/membership notices go through the same mailer; an SMTP outage is logged
  (`neaby mail failed: …`) and never breaks the page.
- Test the live config from Coolify's *Terminal*: `php artisan neaby:mail-test you@example.com` — it prints the
  resolved settings (password hidden) and the exact SMTP error if sending fails.
- After changing `MAIL_*` in Coolify, redeploy (config is cached on boot).

## Payments: fake vs Monnify

- **`PAYMENT_PROVIDER=fake` (default)** — no money moves. Checkout, memberships and sponsored listings
  redirect to `/payments/fake/{ref}` (yellow TEST MODE banner) where you mark the payment paid/failed.
  Everything downstream (order paid, stock deducted, membership active, placement live) runs for real.
- **`PAYMENT_PROVIDER=monnify`** — set `MONNIFY_API_KEY`, `MONNIFY_SECRET_KEY`, `MONNIFY_CONTRACT_CODE`
  and `MONNIFY_BASE_URL`. Buyers are sent to Monnify's hosted checkout and returned to `/payments/return`;
  payment is confirmed by server-side verification **and** the webhook. In the Monnify dashboard set the
  webhook URL to `https://<your-domain>/webhooks/payments/monnify`. Set `PAYMENT_FAKE_SANDBOX=false`.
- Refunds are recorded in neaby (`/admin/refunds`); with Monnify, issue the refund in the Monnify
  dashboard and then mark it processed.

## Messaging, calls and QR commerce

- **Chat** — text, images (resized to ≤1280px and re-encoded to WebP in the browser before upload) and voice
  notes (Opus ~24 kbps, `preload="none"` so they don't download until played). Files live on the upload disk
  under random ULID names but are only ever streamed through `/messages/media/{id}` after a participant check.
- **Realtime without websockets** — the open thread polls every `NEABY_CHAT_POLL_SECONDS`; a light "pulse"
  (unread count + incoming call) runs every `NEABY_CHAT_PULSE_SECONDS`. Both pause when the tab is hidden.
- **Voice calls** — 1:1 WebRTC audio (mono Opus capped at 24 kbps, DTX, echo cancellation). Signalling
  (offer/answer/ICE) goes over plain HTTP polling and is deleted when the call ends; a call record
  ("Voice call · 02:13", "Missed voice call") is left in the thread. Media is peer-to-peer.
  STUN works on most home/mobile networks; **production should add a TURN server**, e.g. coturn:
  ```
  # /etc/turnserver.conf (minimal)
  listening-port=3478
  fingerprint
  lt-cred-mech
  user=neaby:change-me
  realm=neaby.ng
  ```
  then set `NEABY_TURN_URL=turn:turn.neaby.ng:3478?transport=udp,turn:turn.neaby.ng:3478?transport=tcp`,
  `NEABY_TURN_USERNAME=neaby`, `NEABY_TURN_CREDENTIAL=change-me` and open UDP/TCP 3478 (+ relay range 49152–65535/udp).
- **QR commerce** — sellers create codes at `/sell/qr` (optional %/₦ discount, preset options and quantity,
  max uses, expiry). Buyers scan from Cart or Search (camera via `BarcodeDetector`, falling back to a lazily
  loaded jsQR; or upload a photo of the code). Only neaby links are opened. The landing page adds the item to
  the cart with the discount applied; size/quantity stay adjustable unless the seller locked them. Discounts
  are re-validated at checkout (expired/used-up codes fall back to the normal price).
- **Account deletion** — Settings → Delete account asks for the password and the account email, then emails a
  signed link (valid 30 min). Nothing is deleted until the link is opened and confirmed. With `MAIL_MAILER=log`
  the link is only in the log (`storage/logs` or container logs).

## Known limits

- AI titles/descriptions use `FakeListingAi` (offline, builds from the seller's words) unless `LISTING_AI_DRIVER=openai`.
- Monnify refunds are not automated (see above). Payouts/settlement to sellers are outside neaby.
- Messaging/calls use HTTP polling (no websocket server): messages arrive within a few seconds, not instantly. Calls need a TURN server on strict NATs; there's no video, group calling or push notification for calls when the app isn't open.
- The availability endpoint reveals whether an email is registered (throttled to 60/min per IP).
- Email clients that block web fonts (e.g. Gmail) fall back to Trebuchet MS/Arial for the mail wordmark; Apple Mail, iOS and most others render Comfortaa.

## Demo data

A self-contained demo cast so you can preview every part of neaby. **It never calls Listing AI / OpenAI**
(the seeder forces the offline `FakeListingAi`, so no API credits are used) and **sends no email**
(inbox notices are written straight to the database).

### Turn it on / off — one switch

| How | On | Off |
| --- | --- | --- |
| Coolify env (redeploy) | `NEABY_SEED_DEMO=true` | `NEABY_SEED_DEMO=wipe` (removes it), then set `false` |
| Artisan (Coolify terminal / local) | `php artisan neaby:demo seed` | `php artisan neaby:demo wipe` |

- `php artisan neaby:demo seed --fresh` — wipe and rebuild the demo.
- `php artisan neaby:demo status` — shows whether the demo is present.
- Seeding is idempotent: if `neaby1@beuden.com.ng` already exists it does nothing.
- Wipe deletes **only** demo accounts (`neabyN@beuden.com.ng`, plus legacy `*@neaby.test`) and everything they own
  (stores, products, photos, orders, payments, memberships, carts, saves, events, inbox). Real users are untouched.
  Note: an order a real user placed in a demo store is deleted with that store.
- `php artisan migrate --seed` also adds it, but only outside `APP_ENV=production`.

### Logins — the password is the email address

Every demo email is verified. Example: email `neaby1@beuden.com.ng`, password `neaby1@beuden.com.ng`.

| Email (= password) | Name | City | Role in the demo |
| --- | --- | --- | --- |
| `neaby1@beuden.com.ng` | Adaeze Okonkwo | Yaba, Lagos | Admin · Adaeze Styles (Fashion) · Seller Pro |
| `neaby2@beuden.com.ng` | Tunde Bakare | Ikeja, Lagos | Gadget Hub NG (Phones) · resale supplier (yearly) |
| `neaby3@beuden.com.ng` | Chioma Eze | Enugu | Glow by Chi (Beauty) · reseller (monthly) |
| `neaby4@beuden.com.ng` | Musa Ibrahim | Wuse, Abuja | Abuja Home Goods (Home & Kitchen) |
| `neaby5@beuden.com.ng` | Emeka Nwosu | Onitsha, Anambra | Emeka Kicks (Shoes & Bags) |
| `neaby6@beuden.com.ng` | Folake Adeyemi | Bodija, Ibadan | Folake Hair Studio (Hair & Wigs) · CSV import |
| `neaby7@beuden.com.ng` | Ibrahim Danjuma | Kano | Kano Fresh Foods (Food & Groceries) |
| `neaby8@beuden.com.ng` | Ngozi Obi | Port Harcourt | Little Steps Kids (Baby & Kids) |
| `neaby9@beuden.com.ng` | Yetunde Ogunleye | Abeokuta | Wellness by Yetty (Health) |
| `neaby10@beuden.com.ng` | Segun Adebayo | Lekki, Lagos | FitLife Gear (Sports) · Seller Pro |
| `neaby11@beuden.com.ng` | Halima Bello | Kaduna | Halima Gold & Time (Jewellery) |
| `neaby12@beuden.com.ng` | Kelechi Amadi | Owerri | Kelechi Art House (Art & Crafts) |
| `neaby13@beuden.com.ng` | Bisi Alabi | Ilorin | Bisi Books & More (Books) |
| `neaby14@beuden.com.ng` | Uche Okeke | Ladipo, Lagos | Uche Auto Spares (Vehicles & Parts) |
| `neaby15@beuden.com.ng` | Damilola Ojo | Surulere, Lagos | Dami Fixes (Services) |
| `neaby16@beuden.com.ng` | Obinna Chukwu | Computer Village, Ikeja | Obinna Tech World (Electronics) · resale supplier |
| `neaby17@beuden.com.ng` | Aisha Mohammed | Garki, Abuja | Reseller (yearly) + buyer |
| `neaby18@beuden.com.ng` | Funmilayo Lawal | Ikorodu, Lagos | Reseller (monthly, referred by Chioma) + buyer |
| `neaby19@beuden.com.ng` | Chinedu Okafor | Benin City | Buyer |
| `neaby20@beuden.com.ng` | Zainab Yusuf | Jos | Buyer |

### What gets seeded

- 16 stores (one per category), 64 products in all 16 categories, with realistic photos: product images,
  store logos (DP) and covers, and profile photos for all 20 demo users.
- Memberships with paid (fake-provider) payments and entitlements: resale suppliers, resellers, Seller Pro.
- Resale: supplier products open for resale, 6 reseller offers, a referral, supplier requests in every status
  (sent → availability confirmed → terms proposed → accepted → fulfilled, and cancelled).
- 15 orders across every status (paid, processing, ready for pickup, shipped, delivered, completed, cancelled,
  refunded with a refund record, payment failed), with payment transactions.
- Carts, saved items, product views, shares, QR/share visits, enquiries and card downloads (insights have data).
- Sponsored placements (3 active, 1 ended), a completed CSV import batch with a failed row.
- Inbox: welcome, order, resale, membership, sponsored, import, store tips and admin notices — read and unread.
- Chats: three text threads between demo buyers and sellers (with product/store context), and one live
  10%-off QR code (14 days, 50 uses) for each of the first four stores.

### Demo photos (small, fetched on seed — not in Git)

- Photos are free-licence stock images from the Pexels CDN, **downloaded once when the demo is seeded** straight
  into `storage/app/public/demo/` (`products/`, `stores/`, `avatars/`). Nothing is bundled in the repo or zip.
- About 120 compressed JPEGs, ~8–10 MB in total (products 800×800, logos/avatars 320×320, covers 1200×480).
- No internet / CDN down? Each missing photo falls back to an offline generated placeholder, so seeding never fails.
  `NEABY_DEMO_PHOTOS=false` skips downloads entirely (placeholders only).
- `neaby:demo wipe` (or `NEABY_SEED_DEMO=wipe`) deletes every demo photo along with the demo rows, including the
  whole `storage/app/public/demo/` folder.
- **Self-heal:** with `NEABY_SEED_DEMO=true`, every boot checks the demo users' avatars, store logos/covers and
  product photos. If the rows exist but the files are gone (typically a redeploy without a persistent volume),
  only the missing files are re-downloaded to the same paths — no rows change, nothing is wiped. Run it by hand
  any time with `php artisan neaby:demo repair`.
- **Keep photos across deploys (Coolify):** Application → *Storages* → *Add* → *Volume Mount*, Destination path
  **`/var/www/html/storage/app/public`** (name e.g. `neaby-uploads`). This holds real uploads too — without it every
  redeploy starts with an empty folder. Bind mount instead? `chown -R 33:33 <host path>` once.

### Removing the demo for good (before real use)

1. Run `php artisan neaby:demo wipe` (or deploy once with `NEABY_SEED_DEMO=wipe`).
2. Delete these files from GitHub:
   - `database/seeders/NeabyDemoSeeder.php`
   - `app/Console/Commands/NeabyDemoCommand.php`
3. Optional tidy-up (harmless if left — they skip themselves when the files above are gone):
   the `NEABY_SEED_DEMO` block in `docker-entrypoint.sh`, the `NeabyDemoSeeder` lines in
   `database/seeders/DatabaseSeeder.php`, the `NEABY_SEED_DEMO` lines in `.env.example`, and this README section.

## Checks

- `npm run build` — Vite production build (JS only; there is no TypeScript, so no `tsc` step).
- `php artisan route:list`
