<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PcBuilderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\SetLocale;
use App\Models\AbandonedCheckout;
use App\Services\AbandonedCarts;
use Illuminate\Support\Facades\Route;

// Storefront
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/brands', [CategoryController::class, 'brands'])->name('brands.index');
Route::get('/brand/{slug}', [CategoryController::class, 'brand'])->name('brand.show');
Route::get('/search', [CategoryController::class, 'search'])->name('search');
Route::get('/search/suggest', [CategoryController::class, 'suggest'])->name('search.suggest');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');

Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');
Route::post('/compare', [CompareController::class, 'add'])->name('compare.add');
Route::delete('/compare/{id}', [CompareController::class, 'remove'])->name('compare.remove');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{item}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');
Route::delete('/cart/coupon/remove', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
Route::post('/assistant', [AssistantController::class, 'reply'])->name('assistant')->middleware('throttle:12,1');
Route::post('/checkout/contact', [CheckoutController::class, 'contact'])->name('checkout.contact')->middleware('throttle:30,1');
Route::get('/cart/recover/{token}', function (string $token, AbandonedCarts $carts) {
    $record = AbandonedCheckout::where('token', $token)->whereNull('recovered_at')->first();
    if (! $record) {
        return redirect()->route('cart.index')->with('error', 'This cart link has expired.');
    }
    $carts->restore($record);

    return redirect()->route('checkout.index')->with('success', 'Your cart is ready. Complete your order below.');
})->name('cart.recover');
Route::post('/checkout/otp/send', [CheckoutController::class, 'sendOtp'])->name('checkout.otp.send')->middleware('throttle:3,10');
Route::post('/checkout/otp/verify', [CheckoutController::class, 'verifyOtp'])->name('checkout.otp.verify')->middleware('throttle:10,10');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:10,1');
Route::get('/checkout/success/{number}', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/payment/{number}/pay', [PaymentController::class, 'pay'])->name('payment.pay')->middleware('throttle:10,1');
Route::get('/payment/{number}/pay', [PaymentController::class, 'pay'])->name('payment.start');

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(isset(SetLocale::LOCALES[$locale]), 404);
    session(['locale' => $locale]);

    return redirect()->back(fallback: '/')->withCookie(cookie()->forever('locale', $locale));
})->name('locale');

// SEO
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{type}.xml', [SeoController::class, 'sitemap'])->where('type', 'pages|categories|brands|products-\d+')->name('sitemap.part');

Route::prefix('pc-builder')->name('pc-builder.')->controller(PcBuilderController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/choose/{component}', 'choose')->name('choose');
    Route::post('/select/{component}/{product}', 'select')->name('select');
    Route::delete('/remove/{component}', 'remove')->name('remove');
    Route::delete('/clear', 'clear')->name('clear');
    Route::post('/add-to-cart', 'addToCart')->name('cart');
    Route::middleware('auth')->group(function () {
        Route::post('/save', 'save')->name('save');
        Route::post('/load/{savedPc}', 'load')->name('load');
        Route::delete('/saved/{savedPc}', 'destroy')->name('destroy');
    });
});

Route::get('/offers', [SiteController::class, 'offers'])->name('offers');
Route::get('/track-order', [SiteController::class, 'track'])->name('track');
Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
Route::post('/contact', [SiteController::class, 'sendContact'])->name('contact.send')->middleware('throttle:5,1');
Route::post('/newsletter', [SiteController::class, 'subscribe'])->name('newsletter')->middleware('throttle:5,1');
Route::get('/page/{slug}', [SiteController::class, 'page'])->name('page');

// Auth
Route::middleware('guest')->controller(AuthController::class)->group(function () {
    Route::get('/login', 'showLogin')->name('login');
    Route::post('/login', 'login')->middleware('throttle:10,1');
    Route::get('/register', 'showRegister')->name('register');
    Route::post('/register', 'register')->middleware('throttle:10,1');
    Route::get('/forgot-password', 'showForgot')->name('password.request');
    Route::post('/forgot-password', 'sendResetLink')->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', 'showReset')->name('password.reset');
    Route::post('/reset-password', 'resetPassword')->name('password.store');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Customer account
Route::middleware('auth')->prefix('account')->name('account.')->controller(AccountController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/orders', 'orders')->name('orders');
    Route::get('/orders/{number}', 'order')->name('order');
    Route::post('/orders/{number}/cancel', 'cancelOrder')->name('order.cancel');
    Route::get('/profile', 'profile')->name('profile');
    Route::put('/profile', 'updateProfile')->name('profile.update');
    Route::put('/password', 'updatePassword')->name('password.update');
    Route::get('/wishlist', 'wishlist')->name('wishlist');
    Route::post('/wishlist', 'toggleWishlist')->name('wishlist.toggle');
    Route::get('/saved-pcs', 'savedPcs')->name('saved-pcs');
});
Route::middleware('auth')->prefix('account')->name('account.')->controller(ReturnController::class)->group(function () {
    Route::get('/returns', 'index')->name('returns');
    Route::get('/orders/{number}/return', 'create')->name('return.create');
    Route::post('/orders/{number}/return', 'store')->name('return.store')->middleware('throttle:10,1');
    Route::post('/returns/{number}/cancel', 'cancel')->name('return.cancel');
});
Route::middleware('auth')->group(function () {
    Route::post('/product/{product}/review', [ProductController::class, 'review'])->name('product.review');
    Route::post('/product/{product}/question', [ProductController::class, 'question'])->name('product.question');
});

// Admin panel
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    // Product picker for staff orders and flash sales (checked in the controller).
    Route::get('products/search', [Admin\ProductController::class, 'search'])->name('products.search');

    Route::middleware('can:admin.orders')->group(function () {
        Route::resource('orders', Admin\OrderController::class)->only('index', 'create', 'store', 'show', 'update');
        Route::get('orders/{order}/edit', [Admin\OrderController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}/details', [Admin\OrderController::class, 'saveDetails'])->name('orders.details');
        Route::get('orders/{order}/invoice', [Admin\OrderController::class, 'invoice'])->name('orders.invoice');
        Route::post('orders/{order}/courier', [Admin\CourierController::class, 'send'])->name('orders.courier');
        Route::post('orders/{order}/courier/refresh', [Admin\CourierController::class, 'refresh'])->name('orders.courier.refresh');
        Route::get('couriers/pathao/locations', [Admin\CourierController::class, 'pathaoLocations'])->name('couriers.pathao.locations');
        Route::get('customers/lookup', [Admin\CustomerController::class, 'lookup'])->name('customers.lookup');
        Route::resource('customers', Admin\CustomerController::class)->only('index', 'show', 'update')->parameters(['customers' => 'user']);
        Route::get('fraud-check', [Admin\BlockedPhoneController::class, 'check'])->name('fraud.check');
        Route::post('blocked-phones/unblock', [Admin\BlockedPhoneController::class, 'unblock'])->name('blocked-phones.unblock');
        Route::resource('blocked-phones', Admin\BlockedPhoneController::class)->only('index', 'store', 'update', 'destroy');
        Route::get('abandoned-carts', [Admin\AbandonedCartController::class, 'index'])->name('abandoned-carts.index');
        // Returns, refunds, warranty claims and serial numbers.
        Route::get('returns', [Admin\ReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/{return}', [Admin\ReturnController::class, 'show'])->name('returns.show')->whereNumber('return');
        Route::put('returns/{return}', [Admin\ReturnController::class, 'update'])->name('returns.update');
        Route::get('orders/{order}/returns/create', [Admin\ReturnController::class, 'create'])->name('returns.create');
        Route::post('orders/{order}/returns', [Admin\ReturnController::class, 'store'])->name('returns.store');
        Route::put('orders/{order}/items/{item}/serials', [Admin\ReturnController::class, 'serials'])->name('orders.serials');
        Route::get('warranty', [Admin\ReturnController::class, 'warranty'])->name('warranty');
        Route::post('abandoned-carts/{checkout}/remind', [Admin\AbandonedCartController::class, 'remind'])->name('abandoned-carts.remind');
        Route::delete('abandoned-carts/{checkout}', [Admin\AbandonedCartController::class, 'destroy'])->name('abandoned-carts.destroy');
    });

    Route::middleware('can:admin.catalog')->group(function () {
        // Bulk price/stock updates (before the resource so "import" isn't read as a product id).
        Route::get('products/import', [Admin\ProductImportController::class, 'index'])->name('products.import');
        Route::post('products/import', [Admin\ProductImportController::class, 'preview'])->name('products.import.preview');
        Route::post('products/import/apply', [Admin\ProductImportController::class, 'apply'])->name('products.import.apply');
        Route::get('products/export', [Admin\ProductImportController::class, 'export'])->name('products.export');
        Route::resource('products', Admin\ProductController::class)->except('show');
        Route::delete('products/{product}/images/{image}', [Admin\ProductController::class, 'destroyImage'])->name('products.images.destroy');
        Route::resource('categories', Admin\CategoryController::class)->only('index', 'store', 'update', 'destroy');
        Route::resource('brands', Admin\BrandController::class)->only('index', 'store', 'update', 'destroy');
        Route::resource('pc-components', Admin\PcComponentController::class)->only('index', 'store', 'update', 'destroy');

        // Inventory: low stock, stock history, suppliers and purchase orders.
        Route::get('inventory', [Admin\InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory/adjust', [Admin\InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::resource('suppliers', Admin\SupplierController::class)->only('index', 'store', 'update', 'destroy');
        Route::resource('purchase-orders', Admin\PurchaseOrderController::class);
        Route::post('purchase-orders/{purchase_order}/status', [Admin\PurchaseOrderController::class, 'status'])->name('purchase-orders.status');
        Route::post('purchase-orders/{purchase_order}/receive', [Admin\PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');

        // AI Writer: product texts written by AI and approved by staff.
        Route::get('ai-writer', [Admin\AiController::class, 'index'])->name('ai.index');
        Route::post('ai-writer/queue', [Admin\AiController::class, 'queue'])->name('ai.queue');
        Route::post('ai-writer/approve-all', [Admin\AiController::class, 'approveAll'])->name('ai.approve-all');
        Route::post('ai-writer/{draft}/approve', [Admin\AiController::class, 'approve'])->name('ai.approve');
        Route::post('ai-writer/{draft}/reject', [Admin\AiController::class, 'reject'])->name('ai.reject');
        Route::post('ai-writer/{draft}/retry', [Admin\AiController::class, 'retry'])->name('ai.retry')->middleware('throttle:20,1');
        Route::post('products/{product}/ai-write', [Admin\AiController::class, 'write'])->name('products.ai-write')->middleware('throttle:20,1');
    });

    Route::middleware('can:admin.marketing')->group(function () {
        Route::resource('coupons', Admin\CouponController::class)->only('index', 'store', 'update', 'destroy');
        Route::resource('flash-sales', Admin\FlashSaleController::class)->except('show');
        Route::resource('banners', Admin\BannerController::class)->only('index', 'store', 'update', 'destroy');
        Route::resource('offers', Admin\OfferController::class)->only('index', 'store', 'update', 'destroy');
        Route::resource('pages', Admin\PageController::class)->only('index', 'store', 'update', 'destroy');
    });

    Route::middleware('can:admin.engagement')->group(function () {
        Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('reviews/{review}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::put('reviews/{review}/reply', [Admin\ReviewController::class, 'reply'])->name('reviews.reply');
        Route::post('messages/{message}/reply', [Admin\SettingController::class, 'replyMessage'])->name('messages.reply')->middleware('throttle:20,1');
        Route::post('ai/reply/{type}/{id}', [Admin\AiController::class, 'reply'])->name('ai.reply')->whereIn('type', ['question', 'review', 'message'])->whereNumber('id')->middleware('throttle:30,1');
        Route::get('questions', [Admin\ReviewController::class, 'questions'])->name('questions.index');
        Route::patch('questions/{question}', [Admin\ReviewController::class, 'answer'])->name('questions.answer');
        Route::delete('questions/{question}', [Admin\ReviewController::class, 'destroyQuestion'])->name('questions.destroy');
        Route::get('messages', [Admin\SettingController::class, 'messages'])->name('messages');
        Route::patch('messages/{message}', [Admin\SettingController::class, 'readMessage'])->name('messages.read');
        Route::delete('messages/{message}', [Admin\SettingController::class, 'destroyMessage'])->name('messages.destroy');
    });

    Route::middleware('can:admin.reports')->group(function () {
        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports');
        Route::get('reports/export/{type}', [Admin\ReportController::class, 'export'])->whereIn('type', ['orders', 'products'])->name('reports.export');
    });

    Route::middleware('can:admin.settings')->group(function () {
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/test-sms', [Admin\SettingController::class, 'testSms'])->name('settings.test-sms')->middleware('throttle:5,1');
        Route::post('settings/test-ai', [Admin\SettingController::class, 'testAi'])->name('settings.test-ai')->middleware('throttle:5,1');
        Route::post('settings/test-email', [Admin\SettingController::class, 'testEmail'])->name('settings.test-email')->middleware('throttle:5,1');
        Route::post('couriers/{courier}/test', [Admin\CourierController::class, 'test'])->name('couriers.test');
        Route::get('system', [Admin\SystemController::class, 'index'])->name('system');
        Route::post('system/backup', [Admin\SystemController::class, 'backup'])->name('system.backup')->middleware('throttle:3,10');
        Route::get('system/backups/{name}', [Admin\SystemController::class, 'download'])->name('system.backups.download');
        Route::post('system/retry-failed', [Admin\SystemController::class, 'retryFailed'])->name('system.retry-failed');
    });

    Route::middleware('can:admin.staff')->group(function () {
        Route::resource('staff', Admin\StaffController::class)->only('index', 'store', 'update', 'destroy')->parameters(['staff' => 'user']);
        Route::get('activity', [Admin\StaffController::class, 'activity'])->name('activity');
    });
});
