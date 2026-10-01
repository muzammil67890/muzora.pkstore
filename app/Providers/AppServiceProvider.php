<?php

namespace App\Providers;

use App\Models\Coupon;
use App\Models\Order;
use App\Policies\CouponPolicy;
use App\Policies\OrderPolicy;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Policies\PaymentMethodPolicy;
use App\Policies\PaymentProofPolicy;
use App\Models\StoreSetting;
use App\Models\Review;
use App\Policies\ReviewPolicy;
use App\Policies\StoreSettingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\Settings\StoreSettingsService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
        Gate::policy(PaymentProof::class, PaymentProofPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(StoreSetting::class, StoreSettingPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);

        View::composer(['storefront', 'layouts.store', 'admin.*', 'account.*'], function ($view): void {
            $request = app('request');
            $settings = $request->attributes->get('muzora.store_settings');
            if (! is_array($settings)) {
                $settings = app(StoreSettingsService::class)->all();
                $request->attributes->set('muzora.store_settings', $settings);
            }
            $view->with('storeSettings', $settings);
        });
    }
}
