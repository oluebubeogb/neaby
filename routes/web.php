<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api\ResaleApiController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OfferPageController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Resell;
use App\Http\Controllers\SavedController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Messaging\CallsController;
use App\Http\Controllers\Messaging\MessagesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Sell;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
| Public — visitors browse freely without an account.
*/
Route::get('/', LandingController::class)->name('landing');
Route::get('/home', FeedController::class)->name('home');
Route::get('/search', SearchController::class)->name('search');
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/c/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/p/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/s/{store:slug}', [StorefrontController::class, 'show'])->name('stores.show');
Route::get('/p/{product:slug}/peek', [ProductController::class, 'peek'])->middleware('throttle:120,1')->name('products.peek');
Route::get('/u/{username}', [PublicProfileController::class, 'show'])->name('users.show')->where('username', '@?[A-Za-z0-9_]+');

// Phase 2 — share cards (PNG, 1080×1080 / 1080×1920 / 1200×630) and QR codes. Public, cacheable.
Route::middleware('throttle:shares')->group(function () {
    Route::get('/share/p/{product:slug}.png', [ShareController::class, 'productCard'])->name('share.product');
    Route::get('/share/s/{store:slug}.png', [ShareController::class, 'storeCard'])->name('share.store');
    Route::get('/share/r/{listing:slug}.png', [ShareController::class, 'offerCard'])->name('share.offer');
    Route::get('/qr/p/{product:slug}.png', [ShareController::class, 'productQr'])->name('qr.product');
    Route::get('/qr/s/{store:slug}.png', [ShareController::class, 'storeQr'])->name('qr.store');
});
Route::post('/events/share', [ShareController::class, 'event'])->middleware('throttle:events')->name('events.share');

// Phase 6 — public reseller offer pages (always point at the original product).
Route::get('/r/{listing:slug}', [OfferPageController::class, 'show'])->name('offers.show');
Route::post('/r/{listing:slug}/enquire', [OfferPageController::class, 'enquire'])->middleware('throttle:events')->name('offers.enquire');

// Seller QR codes (scan → product, optional discount / preset qty & size).
Route::get('/q/{qr}.png', [QrController::class, 'png'])->middleware('throttle:shares')->name('q.png')->where('qr', '[a-z0-9]{6,16}');
Route::get('/q/{qr}', [QrController::class, 'show'])->name('q.show')->where('qr', '[a-z0-9]{6,16}');

// Live username / email availability (sign-up + settings). Same rules as save.
Route::get('/availability', [ProfileController::class, 'availability'])->middleware('throttle:60,1')->name('profile.availability');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Health: /up (framework, no deps) and /api/health (DB + Redis + storage).
Route::get('/api/health', HealthController::class)->name('health');

// Payment webhooks (CSRF-exempt in bootstrap/app.php).
Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:webhooks')->name('webhooks.payments')->where('provider', '[a-z0-9_-]+');

/*
| Signed-in
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('home'))->name('dashboard'); // Breeze compatibility
    Route::get('/inbox', InboxController::class)->name('inbox');
    Route::post('/inbox/read', [InboxController::class, 'read'])->name('inbox.read');

    // Phase 2 — saved
    Route::get('/saved', [SavedController::class, 'index'])->name('saved.index');
    Route::post('/saved/{product:id}', [SavedController::class, 'toggle'])->middleware('throttle:events')->name('saved.toggle');

    // Phase 4 — cart, checkout, orders
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/{product:id}', [CartController::class, 'add'])->middleware('throttle:checkout')->name('cart.add');
    Route::post('/cart/items/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout.show');
    Route::post('/checkout', [CartController::class, 'place'])->middleware('throttle:checkout')->name('checkout.place');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');

    // Payments (return URL + local fake sandbox)
    Route::get('/payments/return', [PaymentController::class, 'back'])->name('payments.return');
    Route::get('/payments/fake/{reference}', [PaymentController::class, 'fakeShow'])->name('payments.fake.show')->where('reference', '[A-Za-z0-9_-]+');
    Route::post('/payments/fake/{reference}', [PaymentController::class, 'fakeComplete'])->middleware('throttle:checkout')->name('payments.fake.complete')->where('reference', '[A-Za-z0-9_-]+');

    // Phase 3 — memberships
    Route::get('/membership', [MembershipController::class, 'index'])->name('membership.index');
    Route::post('/membership/{plan:code}', [MembershipController::class, 'purchase'])->middleware('throttle:checkout')->name('membership.purchase');
    Route::post('/membership/subscriptions/{subscription}/cancel', [MembershipController::class, 'cancel'])->name('membership.cancel');

    // Phase 6 — reseller hub + supplier requests
    Route::get('/resell', [Resell\HubController::class, 'index'])->name('resell.index');
    Route::get('/resell/offers', [Resell\OfferController::class, 'index'])->name('resell.offers.index');
    Route::get('/resell/offers/new/{product:id}', [Resell\OfferController::class, 'create'])->name('resell.offers.create');
    Route::post('/resell/offers/new/{product:id}', [Resell\OfferController::class, 'store'])->name('resell.offers.store');
    Route::get('/resell/offers/{listing:id}/edit', [Resell\OfferController::class, 'edit'])->name('resell.offers.edit');
    Route::post('/resell/offers/{listing:id}', [Resell\OfferController::class, 'update'])->name('resell.offers.update');
    Route::delete('/resell/offers/{listing:id}', [Resell\OfferController::class, 'destroy'])->name('resell.offers.destroy');
    Route::get('/requests', [Resell\RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{supplierRequest}', [Resell\RequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/new/{product:id}', [Resell\RequestController::class, 'store'])->middleware('throttle:requests')->name('requests.store');
    Route::post('/requests/{supplierRequest}/act', [Resell\RequestController::class, 'act'])->middleware('throttle:requests')->name('requests.act');

    // Protected resale terms (never cached publicly)
    Route::get('/api/products/{product:id}/resale', [ResaleApiController::class, 'show'])->name('api.resale.show');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/settings', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('throttle:5,1')->name('profile.destroy');
    Route::get('/profile/delete/confirm', [ProfileController::class, 'confirmDestroy'])->middleware('signed')->name('profile.destroy.confirm');
    Route::post('/profile/delete/confirm', [ProfileController::class, 'finalDestroy'])->middleware('signed')->name('profile.destroy.final');

    // In-app messaging (1:1) + voice calls (WebRTC, HTTP-polled signalling).
    Route::get('/messages', [MessagesController::class, 'index'])->name('messages.index');
    Route::post('/messages/start', [MessagesController::class, 'start'])->middleware('throttle:30,1')->name('messages.start');
    Route::get('/messages/pulse', [MessagesController::class, 'pulse'])->name('messages.pulse');
    Route::get('/messages/media/{message}', [MessagesController::class, 'media'])->name('messages.media');
    Route::get('/messages/{conversation}', [MessagesController::class, 'index'])->name('messages.show');
    Route::get('/messages/{conversation}/feed', [MessagesController::class, 'feed'])->name('messages.feed');
    Route::post('/messages/{conversation}', [MessagesController::class, 'send'])->name('messages.send');
    Route::post('/messages/{conversation}/call', [CallsController::class, 'start'])->middleware('throttle:10,1')->name('calls.start');
    Route::get('/calls/{call}', [CallsController::class, 'show'])->name('calls.show');
    Route::post('/calls/{call}/answer', [CallsController::class, 'answer'])->name('calls.answer');
    Route::post('/calls/{call}/decline', [CallsController::class, 'decline'])->name('calls.decline');
    Route::post('/calls/{call}/end', [CallsController::class, 'end'])->name('calls.end');
    Route::post('/calls/{call}/signal', [CallsController::class, 'signal'])->middleware('throttle:600,1')->name('calls.signal');
    Route::get('/calls/{call}/signals', [CallsController::class, 'signals'])->name('calls.signals');

    Route::post('/q/{qr}/add', [QrController::class, 'add'])->middleware('throttle:checkout')->name('q.add')->where('qr', '[a-z0-9]{6,16}');

    Route::prefix('sell')->name('sell.')->group(function () {
        Route::get('/', Sell\DashboardController::class)->name('dashboard');

        Route::get('/store/create', [Sell\StoreController::class, 'create'])->name('store.create');
        Route::post('/store', [Sell\StoreController::class, 'store'])->middleware('throttle:uploads')->name('store.store');
        Route::get('/store', [Sell\StoreController::class, 'edit'])->name('store.edit');
        Route::post('/store/update', [Sell\StoreController::class, 'update'])->middleware('throttle:uploads')->name('store.update');

        Route::get('/products/create', [Sell\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [Sell\ProductController::class, 'store'])->middleware('throttle:uploads')->name('products.store');
        Route::get('/products/{product:id}/edit', [Sell\ProductController::class, 'edit'])->name('products.edit');
        // POST (not PUT) so multipart uploads work everywhere.
        Route::post('/products/{product:id}', [Sell\ProductController::class, 'update'])->middleware('throttle:uploads')->name('products.update');
        Route::post('/products/{product:id}/status', [Sell\ProductController::class, 'status'])->name('products.status');
        Route::delete('/products/{product:id}', [Sell\ProductController::class, 'destroy'])->name('products.destroy');

        Route::post('/ai/titles', [Sell\AiController::class, 'titles'])->middleware('throttle:ai')->name('ai.titles');
        Route::post('/ai/description', [Sell\AiController::class, 'description'])->middleware('throttle:ai')->name('ai.description');
        Route::post('/pricing', [Sell\AiController::class, 'pricing'])->middleware('throttle:ai')->name('pricing');

        Route::get('/qr', [Sell\QrController::class, 'index'])->name('qr.index');
        Route::post('/qr', [Sell\QrController::class, 'store'])->name('qr.store');
        Route::post('/qr/{qr}', [Sell\QrController::class, 'update'])->name('qr.update');
        Route::delete('/qr/{qr}', [Sell\QrController::class, 'destroy'])->name('qr.destroy');

        // Phase 4 — seller orders
        Route::get('/orders', [Sell\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [Sell\OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [Sell\OrderController::class, 'status'])->name('orders.status');

        // Phase 5 — import, bulk edit, insights
        Route::get('/import', [Sell\ImportController::class, 'index'])->name('import.index');
        Route::post('/import', [Sell\ImportController::class, 'store'])->middleware('throttle:uploads')->name('import.store');
        Route::get('/import/template.csv', [Sell\ImportController::class, 'template'])->name('import.template');
        Route::get('/import/{batch}', [Sell\ImportController::class, 'show'])->name('import.show');
        Route::get('/import/{batch}/errors.csv', [Sell\ImportController::class, 'errors'])->name('import.errors');
        Route::get('/bulk', [Sell\BulkController::class, 'index'])->name('bulk.index');
        Route::post('/bulk', [Sell\BulkController::class, 'update'])->name('bulk.update');
        Route::get('/insights', [Sell\InsightsController::class, 'index'])->name('insights');
        Route::get('/insights/export.csv', [Sell\InsightsController::class, 'export'])->name('insights.export');
        Route::get('/products/{product:id}/promote', [Sell\InsightsController::class, 'promote'])->name('promote');
        Route::post('/products/{product:id}/promote', [Sell\InsightsController::class, 'promoteStart'])->middleware('throttle:checkout')->name('promote.start');

        // Phase 6 — supplier resale programme
        Route::get('/resale', [Sell\ResaleSettingsController::class, 'index'])->name('resale.index');
        Route::post('/resale/{product:id}', [Sell\ResaleSettingsController::class, 'update'])->name('resale.update');
        Route::post('/resale/resellers/{relationship}', [Sell\ResaleSettingsController::class, 'relationship'])->name('resale.relationship');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('index');
        Route::get('/products', [Admin\ProductModerationController::class, 'index'])->name('products.index');
        Route::post('/products/{product:id}', [Admin\ProductModerationController::class, 'update'])->name('products.update');
        Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::post('/categories/{category:id}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::post('/stores/{store:id}', [Admin\StoreModerationController::class, 'update'])->name('stores.update');
        Route::get('/plans', [Admin\CommerceController::class, 'plans'])->name('plans.index');
        Route::post('/plans/grant', [Admin\CommerceController::class, 'grant'])->name('plans.grant');
        Route::post('/plans/{plan}', [Admin\CommerceController::class, 'updatePlan'])->name('plans.update');
        Route::get('/analytics', [Admin\CommerceController::class, 'analytics'])->name('analytics');
        Route::get('/refunds', [Admin\CommerceController::class, 'refunds'])->name('refunds.index');
        Route::post('/refunds/{refund}', [Admin\CommerceController::class, 'updateRefund'])->name('refunds.update');
        Route::get('/sponsored', [Admin\CommerceController::class, 'sponsored'])->name('sponsored.index');
        Route::post('/sponsored/{placement}', [Admin\CommerceController::class, 'updateSponsored'])->name('sponsored.update');
    });
});

require __DIR__.'/auth.php';
