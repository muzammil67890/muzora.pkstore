<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Review;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseSixFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'checkout.shipping.standard_fee_minor' => 25000,
            'checkout.shipping.free_threshold_minor' => 299900,
        ]);
        $this->paymentMethod = PaymentMethod::query()->create([
            'name' => 'Bank Transfer',
            'slug' => 'bank-transfer',
            'type' => 'bank',
            'account_title' => 'MUZORA.PK',
            'account_number' => '123456789',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_coupon_is_normalized_revalidated_and_used_only_after_successful_order(): void
    {
        $coupon = Coupon::query()->create([
            'code' => ' save50 ',
            'type' => 'fixed',
            'value' => 5000,
            'minimum_order_amount_minor' => 10000,
            'usage_limit' => 2,
            'usage_limit_per_customer' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer('coupon-order@example.com');
        $product = $this->makeProduct(price: '150.00', slug: 'coupon-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();

        $this->post(route('checkout.coupon.apply'), ['coupon_code' => '  save50  '])->assertRedirect(route('checkout.create'));
        $this->assertSame(0, $coupon->fresh()->used_count, 'Applying a code must not reserve a use.');
        $this->assertDatabaseCount('coupon_usages', 0);

        $this->get(route('checkout.create'))->assertOk()->assertSee('SAVE50')->assertSee('Discount');
        $token = $this->checkoutToken();
        $this->post(route('checkout.store'), $this->checkoutPayload($token))->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('SAVE50', $order->coupon_code);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame(30000, $order->subtotal_minor);
        $this->assertSame(5000, $order->discount_amount_minor);
        $this->assertSame(25000, $order->shipping_amount_minor);
        $this->assertSame(50000, $order->total_amount_minor);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'coupon_code' => 'SAVE50',
            'discount_amount_minor' => 5000,
        ]);

        $this->actingAs($customer, 'web')->post(route('checkout.coupon.apply'), ['coupon_code' => 'SAVE50'])
            ->assertSessionHasErrors('coupon_code');
    }

    public function test_coupon_is_not_consumed_when_checkout_fails_and_customer_cannot_manage_coupons(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'LIMITED', 'type' => 'percentage', 'value' => 10,
            'usage_limit' => 1, 'usage_limit_per_customer' => 1, 'is_active' => true,
        ]);
        $customer = $this->makeCustomer('coupon-failure@example.com');
        $product = $this->makeProduct(slug: 'coupon-failure-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'limited'])->assertRedirect();
        $product->update(['is_active' => false]);

        $this->from(route('checkout.create'))->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHasErrors('cart');
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseCount('orders', 0);

        $this->get(route('admin.coupons.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.settings.general'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reviews.index'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.general.update'), [
            'store_name' => 'Injected Store', 'currency' => 'USD',
        ])->assertRedirect(route('admin.login'));
        $this->assertDatabaseMissing('store_settings', ['setting_key' => 'store_name', 'setting_value' => 'Injected Store']);
    }

    public function test_percentage_coupon_uses_server_calculated_discount_cap_and_order_snapshot(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'PERCENT-CAP', 'type' => 'percentage', 'value' => 25,
            'maximum_discount_amount_minor' => 5000, 'is_active' => true,
        ]);
        $customer = $this->makeCustomer('percentage-coupon@example.com');
        $product = $this->makeProduct(price: '300.00', slug: 'percentage-coupon-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'percent-cap'])->assertRedirect();

        $this->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken(), [
            'discount_amount_minor' => 0,
            'subtotal_minor' => 1,
            'total_amount_minor' => 0,
        ]))->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(60000, $order->subtotal_minor);
        $this->assertSame(5000, $order->discount_amount_minor);
        $this->assertSame(25000, $order->shipping_amount_minor);
        $this->assertSame(80000, $order->total_amount_minor);
        $this->assertSame('PERCENT-CAP', $order->coupon_code);

        $coupon->update(['is_active' => false, 'value' => 100]);
        $this->assertSame(60000, $order->fresh()->subtotal_minor);
        $this->assertSame(5000, $order->fresh()->discount_amount_minor);
        $this->assertSame(80000, $order->fresh()->total_amount_minor);
        $this->assertSame('PERCENT-CAP', $order->fresh()->coupon_code);
    }

    public function test_large_fixed_discount_is_capped_at_subtotal_and_cannot_make_total_negative(): void
    {
        StoreSetting::query()->create(['setting_key' => 'shipping_standard_fee_minor', 'group' => 'shipping', 'setting_value' => '0']);
        StoreSetting::query()->create(['setting_key' => 'shipping_free_threshold_minor', 'group' => 'shipping', 'setting_value' => '0']);
        Coupon::query()->create(['code' => 'TOO-LARGE', 'type' => 'fixed', 'value' => 999999, 'is_active' => true]);
        $customer = $this->makeCustomer('nonnegative-total@example.com');
        $product = $this->makeProduct(price: '30.00', slug: 'nonnegative-total-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'TOO-LARGE'])->assertRedirect();
        $this->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(3000, $order->subtotal_minor);
        $this->assertSame(3000, $order->discount_amount_minor);
        $this->assertSame(0, $order->shipping_amount_minor);
        $this->assertSame(0, $order->total_amount_minor);
        $this->assertGreaterThanOrEqual(0, $order->total_amount_minor);
    }

    public function test_customer_can_remove_coupon_without_consuming_usage(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'REMOVE-ME', 'type' => 'fixed', 'value' => 5000, 'is_active' => true,
        ]);
        $customer = $this->makeCustomer('remove-coupon@example.com');
        $product = $this->makeProduct(price: '100.00', slug: 'remove-coupon-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'REMOVE-ME'])->assertRedirect();
        $this->delete(route('checkout.coupon.remove'))->assertRedirect(route('checkout.create'));
        $this->assertFalse($this->app['session.store']->has('checkout.coupon_code'));
        $this->assertSame(0, $coupon->fresh()->used_count);

        $this->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertNull($order->coupon_code);
        $this->assertNull($order->coupon_id);
        $this->assertSame(0, $order->discount_amount_minor);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_coupon_is_revalidated_against_current_product_prices_at_order_creation(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'PRICE-RECHECK', 'type' => 'percentage', 'value' => 20,
            'minimum_order_amount_minor' => 15000, 'is_active' => true,
        ]);
        $customer = $this->makeCustomer('coupon-recheck@example.com');
        $product = $this->makeProduct(price: '200.00', slug: 'coupon-recheck-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'PRICE-RECHECK'])->assertRedirect();
        $product->update(['price' => '100.00']);

        $this->from(route('checkout.create'))->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHasErrors('coupon_code');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_total_usage_limit_applies_across_different_customers(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'ONE-USE', 'type' => 'percentage', 'value' => 10,
            'usage_limit' => 1, 'is_active' => true,
        ]);
        $product = $this->makeProduct(price: '100.00', slug: 'one-use-coupon-product');
        $firstCustomer = $this->makeCustomer('first-one-use@example.com');
        $this->actingAs($firstCustomer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'ONE-USE'])->assertRedirect();
        $this->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))->assertRedirect();
        $this->assertSame(1, $coupon->fresh()->used_count);

        $secondCustomer = $this->makeCustomer('second-one-use@example.com');
        $this->actingAs($secondCustomer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->from(route('checkout.create'))->post(route('checkout.coupon.apply'), ['coupon_code' => 'ONE-USE'])
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHasErrors('coupon_code');
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('coupon_usages', 1);
    }

    public function test_active_admin_can_create_coupon_with_normalized_code_and_minor_unit_amounts(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->post(route('admin.coupons.store'), [
            'code' => ' spring-12 ',
            'type' => 'fixed',
            'value' => '12.34',
            'minimum_order_amount' => '100.00',
            'maximum_discount_amount' => '20.50',
            'usage_limit' => 50,
            'usage_limit_per_customer' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => '1',
        ])->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseHas('coupons', [
            'code' => 'SPRING-12',
            'type' => 'fixed',
            'value' => 1234,
            'minimum_order_amount_minor' => 10000,
            'maximum_discount_amount_minor' => 2050,
            'usage_limit' => 50,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
        ]);
    }

    public function test_coupon_rejects_inactive_expired_and_under_minimum_codes_on_server(): void
    {
        $customer = $this->makeCustomer('coupon-invalid@example.com');
        $product = $this->makeProduct(price: '20.00', slug: 'coupon-invalid-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        Coupon::query()->create([
            'code' => 'FUTURE', 'type' => 'percentage', 'value' => 20,
            'starts_at' => now()->addDay(), 'is_active' => true,
        ]);
        Coupon::query()->create([
            'code' => 'EXPIRED', 'type' => 'percentage', 'value' => 20,
            'expires_at' => now()->subDay(), 'is_active' => true,
        ]);
        Coupon::query()->create([
            'code' => 'MINIMUM', 'type' => 'percentage', 'value' => 20,
            'minimum_order_amount_minor' => 25000, 'is_active' => true,
        ]);
        Coupon::query()->create([
            'code' => 'INACTIVE', 'type' => 'percentage', 'value' => 20,
            'is_active' => false,
        ]);
        Coupon::query()->create([
            'code' => 'EXHAUSTED', 'type' => 'percentage', 'value' => 20,
            'usage_limit' => 1, 'used_count' => 1, 'is_active' => true,
        ]);

        foreach (['FUTURE', 'EXPIRED', 'MINIMUM', 'INACTIVE', 'EXHAUSTED', 'DOES-NOT-EXIST'] as $code) {
            $this->from(route('checkout.create'))->post(route('checkout.coupon.apply'), ['coupon_code' => $code])
                ->assertRedirect(route('checkout.create'))
                ->assertSessionHasErrors('coupon_code');
        }
        $this->assertDatabaseCount('coupon_usages', 0);
    }

    public function test_verified_purchase_reviews_are_pending_until_admin_approval_and_only_approved_reviews_are_public(): void
    {
        $customer = $this->makeCustomer('review-owner@example.com');
        $product = $this->makeProduct(slug: 'reviewed-product');
        $order = $this->makeDeliveredOrder($customer, $product);
        $payload = [
            'order_id' => $order->id,
            'rating' => 5,
            'title' => 'Excellent sound',
            'body' => 'Clear audio and a reliable battery for everyday use.',
            'status' => 'approved',
            'user_id' => 999999,
        ];

        $this->actingAs($customer, 'web')->post(route('reviews.store', $product), $payload)
            ->assertRedirect(route('product.show', $product));
        $review = Review::query()->firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertSame($customer->id, $review->user_id);
        $this->assertSame(0, $product->approvedReviews()->count());
        $this->get(route('product.show', $product))->assertOk()->assertDontSee('Clear audio and a reliable battery');
        $this->actingAs($customer, 'web')->post(route('reviews.store', $product), $payload)
            ->assertSessionHasErrors('review');

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->patch(route('admin.reviews.moderate', $review), [
            'status' => 'approved',
            'moderation_note' => 'Verified against delivered order.',
        ])->assertRedirect();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertSame($admin->id, $review->fresh()->moderated_by);
        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('Clear audio and a reliable battery')
            ->assertSee('5.0')
            ->assertSee('1 approved review');

        $this->actingAs($admin, 'admin')->patch(route('admin.reviews.moderate', $review), [
            'status' => 'rejected',
            'moderation_note' => 'The review could not be verified.',
        ])->assertRedirect();
        $this->assertSame('rejected', $review->fresh()->status);
        $this->get(route('product.show', $product))->assertOk()->assertDontSee('Clear audio and a reliable battery');

        $inactiveAdmin = Admin::query()->create([
            'name' => 'Inactive Admin',
            'email' => 'inactive-phase-six-admin@example.test',
            'password' => 'AdminPass123',
        ]);
        $inactiveAdmin->forceFill(['is_active' => false])->save();
        $this->actingAs($inactiveAdmin, 'admin')->patch(route('admin.reviews.moderate', $review), [
            'status' => 'approved',
        ])->assertRedirect(route('admin.login'));
        $this->assertSame('rejected', $review->fresh()->status);
    }

    public function test_unauthenticated_customer_cannot_submit_a_review(): void
    {
        $product = $this->makeProduct(slug: 'unauthenticated-review-product');
        $this->post(route('reviews.store', $product), [
            'order_id' => 1, 'rating' => 5, 'body' => 'Unauthenticated review attempt.',
        ])->assertRedirect(route('login'));
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_reviews_require_ownership_delivered_order_and_integer_rating_between_one_and_five(): void
    {
        $owner = $this->makeCustomer('review-eligible@example.com');
        $other = $this->makeCustomer('review-other@example.com');
        $product = $this->makeProduct(slug: 'review-eligibility-product');
        $pendingOrder = $this->makeDeliveredOrder($owner, $product, status: 'pending');
        $this->actingAs($owner, 'web')->post(route('reviews.store', $product), [
            'order_id' => $pendingOrder->id, 'rating' => 5, 'body' => 'This order is not delivered yet.',
        ])->assertSessionHasErrors('order_id');

        $deliveredOrder = $this->makeDeliveredOrder($owner, $product);
        $this->actingAs($other, 'web')->post(route('reviews.store', $product), [
            'order_id' => $deliveredOrder->id, 'rating' => 5, 'body' => 'This was someone else’s purchase.',
        ])->assertSessionHasErrors('order_id');

        $this->actingAs($owner, 'web')->post(route('reviews.store', $product), [
            'order_id' => $deliveredOrder->id, 'rating' => 6, 'body' => 'Rating is too high for the form.',
        ])->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_active_admin_can_update_database_backed_store_shipping_settings_and_currency(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->put(route('admin.settings.shipping.update'), [
            'shipping_standard_fee' => '-1.00',
            'shipping_free_threshold' => 'NaN',
            'minimum_order_amount' => '-5',
        ])->assertSessionHasErrors(['shipping_standard_fee', 'shipping_free_threshold', 'minimum_order_amount']);
        $this->put(route('admin.settings.general.update'), [
            'store_name' => 'Invalid currency',
            'currency' => 'XXX',
        ])->assertSessionHasErrors('currency');

        $this->put(route('admin.settings.general.update'), [
            'store_name' => 'Muzora Test Store',
            'store_email' => 'support@example.test',
            'store_phone' => '+92 300 1234567',
            'store_address' => 'Karachi, Sindh',
            'currency' => 'USD',
            'footer_text' => 'New trusted store footer.',
        ])->assertRedirect();
        $this->actingAs($admin, 'admin')->put(route('admin.settings.shipping.update'), [
            'shipping_standard_fee' => '80.50',
            'shipping_free_threshold' => '250.00',
            'minimum_order_amount' => '100.25',
        ])->assertRedirect();

        $this->get(route('home'))->assertOk()->assertSee('Muzora Test Store')->assertSee('New trusted store footer.');
        $this->actingAs($admin, 'admin')->get(route('admin.settings.shipping'))->assertOk()->assertSee('80.50');

        $belowMinimumCustomer = $this->makeCustomer('below-minimum-settings@example.com');
        $belowMinimumProduct = $this->makeProduct(price: '100.00', slug: 'below-minimum-settings-product');
        $this->actingAs($belowMinimumCustomer, 'web')->postJson('/cart/add', ['product' => $belowMinimumProduct->slug, 'quantity' => 1])->assertCreated();
        $this->from(route('checkout.create'))->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHasErrors('cart');
        $this->assertDatabaseMissing('orders', ['user_id' => $belowMinimumCustomer->id]);

        $customer = $this->makeCustomer('settings-checkout@example.com');
        $product = $this->makeProduct(price: '150.00', slug: 'settings-checkout-product');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->get(route('checkout.create'))->assertOk()->assertSee('USD 150.00');
        $this->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame('USD', $order->currency);
        $this->assertSame(8050, $order->shipping_amount_minor);
        $this->assertSame(15000, $order->subtotal_minor);
        $this->assertSame(23050, $order->total_amount_minor);

        $secondCustomer = $this->makeCustomer('free-threshold-settings@example.com');
        $thresholdProduct = $this->makeProduct(price: '250.00', slug: 'configured-free-shipping-product');
        $this->actingAs($secondCustomer, 'web')->postJson('/cart/add', ['product' => $thresholdProduct->slug, 'quantity' => 1])->assertCreated();
        $this->post(route('checkout.store'), $this->checkoutPayload($this->checkoutToken()))->assertRedirect();
        $thresholdOrder = Order::query()->where('user_id', $secondCustomer->id)->firstOrFail();
        $this->assertSame(25000, $thresholdOrder->subtotal_minor);
        $this->assertSame(0, $thresholdOrder->shipping_amount_minor);
        $this->assertSame(25000, $thresholdOrder->total_amount_minor);
    }

    private function makeProduct(string $price = '100.00', string $slug = 'phase-six-product'): Product
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio-'.Str::random(6), 'icon' => 'headphones', 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Test Brand', 'slug' => 'brand-'.Str::random(6), 'is_active' => true]);

        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Phase six product',
            'slug' => $slug,
            'sku' => strtoupper(Str::random(8)),
            'price' => $price,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    private function makeCustomer(string $email): User
    {
        return User::query()->create([
            'name' => 'Phase Six Customer',
            'email' => $email,
            'phone' => '+923001234567',
            'address' => '1 Store Road',
            'password' => 'CustomerPass123',
            'email_verified_at' => now(),
        ]);
    }

    private function makeAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Active Admin',
            'email' => 'phase-six-admin@example.test',
            'password' => 'AdminPass123',
            'is_active' => true,
        ]);
    }

    private function makeDeliveredOrder(User $user, Product $product, string $status = 'delivered'): Order
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(12)),
            'idempotency_key' => (string) Str::uuid(),
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'shipping_address' => $user->address,
            'city' => 'Karachi',
            'province' => 'Sindh',
            'postal_code' => '74000',
            'subtotal_minor' => 10000,
            'shipping_amount_minor' => 0,
            'discount_amount_minor' => 0,
            'total_amount_minor' => 10000,
            'currency' => 'PKR',
            'payment_method_id' => $this->paymentMethod->id,
            'payment_status' => 'verified',
            'order_status' => $status,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price_minor' => 10000,
            'quantity' => 1,
            'line_total_minor' => 10000,
        ]);

        return $order;
    }

    private function checkoutToken(): string
    {
        $this->get(route('checkout.create'))->assertOk();

        return $this->app['session.store']->get('checkout.idempotency_token');
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'checkout_token' => $token,
            'customer_name' => 'Phase Six Customer',
            'customer_email' => 'phase-six-order@example.com',
            'customer_phone' => '+923001234567',
            'shipping_address' => '1 Test Road',
            'city' => 'Karachi',
            'province' => 'Sindh',
            'postal_code' => '74000',
            'payment_method_id' => $this->paymentMethod->id,
        ], $overrides);
    }
}
