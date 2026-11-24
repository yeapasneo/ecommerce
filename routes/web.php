<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PcBuilderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SiteController;
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
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:10,1');
Route::get('/checkout/success/{number}', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/payment/{number}/pay', [PaymentController::class, 'pay'])->name('payment.pay')->middleware('throttle:10,1');
Route::get('/payment/{number}/pay', [PaymentController::class, 'pay'])->name('payment.start');

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
Route::middleware('auth')->group(function () {
    Route::post('/product/{product}/review', [ProductController::class, 'review'])->name('product.review');
    Route::post('/product/{product}/question', [ProductController::class, 'question'])->name('product.question');
});

// Admin panel
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::resource('products', Admin\ProductController::class)->except('show');
    Route::delete('products/{product}/images/{image}', [Admin\ProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::resource('categories', Admin\CategoryController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('brands', Admin\BrandController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('orders', Admin\OrderController::class)->only('index', 'show', 'update');
    Route::get('orders/{order}/invoice', [Admin\OrderController::class, 'invoice'])->name('orders.invoice');
    Route::resource('customers', Admin\CustomerController::class)->only('index', 'show', 'update')->parameters(['customers' => 'user']);
    Route::resource('coupons', Admin\CouponController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('banners', Admin\BannerController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('offers', Admin\OfferController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('pages', Admin\PageController::class)->only('index', 'store', 'update', 'destroy');
    Route::resource('pc-components', Admin\PcComponentController::class)->only('index', 'store', 'update', 'destroy');
    Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::get('questions', [Admin\ReviewController::class, 'questions'])->name('questions.index');
    Route::patch('questions/{question}', [Admin\ReviewController::class, 'answer'])->name('questions.answer');
    Route::delete('questions/{question}', [Admin\ReviewController::class, 'destroyQuestion'])->name('questions.destroy');
    Route::post('orders/{order}/courier', [Admin\CourierController::class, 'send'])->name('orders.courier');
    Route::post('orders/{order}/courier/refresh', [Admin\CourierController::class, 'refresh'])->name('orders.courier.refresh');
    Route::post('couriers/{courier}/test', [Admin\CourierController::class, 'test'])->name('couriers.test');
    Route::get('couriers/pathao/locations', [Admin\CourierController::class, 'pathaoLocations'])->name('couriers.pathao.locations');
    Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings');
    Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
    Route::get('messages', [Admin\SettingController::class, 'messages'])->name('messages');
    Route::patch('messages/{message}', [Admin\SettingController::class, 'readMessage'])->name('messages.read');
    Route::delete('messages/{message}', [Admin\SettingController::class, 'destroyMessage'])->name('messages.destroy');
});
