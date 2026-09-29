<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\PaymentProofController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutCouponController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\StoreSettingsController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentReviewController;
use App\Http\Controllers\Auth\AdminSessionController;
use App\Http\Controllers\Auth\CustomerPasswordController;
use App\Http\Controllers\Auth\CustomerRegistrationController;
use App\Http\Controllers\Auth\CustomerSessionController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'shop'])->name('shop');
Route::get('/product/{product}', [StorefrontController::class, 'show'])->name('product.show');
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart/add', [CartController::class, 'store'])->name('cart.items.store');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->whereNumber('cartItem')->name('cart.items.update');
Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->whereNumber('cartItem')->name('cart.items.destroy');

Route::middleware('auth:web')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/coupon', [CheckoutCouponController::class, 'apply'])->name('checkout.coupon.apply');
    Route::delete('/checkout/coupon', [CheckoutCouponController::class, 'remove'])->name('checkout.coupon.remove');
    Route::post('/product/{product}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/order/success/{order}', [CheckoutController::class, 'success'])->name('orders.success');
});

Route::middleware('guest:web')->group(function (): void {
    Route::get('/register', [CustomerRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [CustomerRegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');

    Route::get('/login', [CustomerSessionController::class, 'create'])->name('login');
    Route::post('/login', [CustomerSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::get('/forgot-password', [CustomerPasswordController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [CustomerPasswordController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [CustomerPasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [CustomerPasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.store');
});

Route::post('/logout', [CustomerSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::prefix('account')->name('account.')->middleware('auth:web')->group(function (): void {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [AccountController::class, 'order'])->where('order', '[A-Za-z0-9-]+')->name('orders.show');
    Route::post('/orders/{order}/payment-proof', [PaymentProofController::class, 'store'])->where('order', '[A-Za-z0-9-]+')->name('orders.payment-proof.store');
    Route::get('/orders/{order}/payment-proofs/{proof}', [PaymentProofController::class, 'download'])->where('order', '[A-Za-z0-9-]+')->whereNumber('proof')->name('orders.payment-proofs.download');
    Route::get('/profile', [AccountController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/password', [AccountController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');
});

Route::get('/account/wishlist', fn () => redirect()->route('wishlist.index'))
    ->middleware('wishlist.auth')
    ->name('account.wishlist');

Route::middleware('wishlist.auth')->group(function (): void {
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{product}/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
    Route::post('/wishlist/{product}/move-to-cart', [WishlistController::class, 'moveToCart'])->name('wishlist.move-to-cart');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });

    Route::post('/logout', [AdminSessionController::class, 'destroy'])
        ->middleware(['auth:admin', 'admin.active'])
        ->name('logout');

    Route::middleware(['auth:admin', 'admin.active'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
        Route::get('/categories', [CatalogController::class, 'categories'])->name('categories.index');
        Route::get('/brands', [CatalogController::class, 'brands'])->name('brands.index');
        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::get('/payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::get('/payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('payment-methods.edit');
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::get('/coupons/create', [CouponController::class, 'create'])->name('coupons.create');
        Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::get('/coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
        Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::patch('/reviews/{review}', [AdminReviewController::class, 'moderate'])->name('reviews.moderate');
        Route::get('/settings/general', [StoreSettingsController::class, 'general'])->name('settings.general');
        Route::put('/settings/general', [StoreSettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::get('/settings/shipping', [StoreSettingsController::class, 'shipping'])->name('settings.shipping');
        Route::put('/settings/shipping', [StoreSettingsController::class, 'updateShipping'])->name('settings.shipping.update');
        Route::get('/payments', [PaymentReviewController::class, 'index'])->name('payments.index');
        Route::get('/payments/{order}', [PaymentReviewController::class, 'show'])->name('payments.show');
        Route::patch('/payments/{order}/review', [PaymentReviewController::class, 'review'])->name('payments.review');
        Route::get('/orders/{order}/payment-proofs/{proof}', [PaymentReviewController::class, 'download'])->whereNumber('proof')->name('payments.proofs.download');
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status.update');
    });
});
