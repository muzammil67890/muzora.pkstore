<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class CheckoutAndOrdersTest extends TestCase
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
            'name' => 'Bank Alfalah',
            'slug' => 'bank-alfalah',
            'type' => 'bank',
            'account_title' => 'MUZORA.PK',
            'account_number' => '123456789',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_guest_checkout_redirects_to_login_and_returns_to_checkout_after_login(): void
    {
        $product = $this->makeProduct(slug: 'guest-checkout-item');
        $customer = $this->makeCustomer('guest-checkout@example.com');
        $this->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();

        $this->get('/checkout')->assertRedirect(route('login'));
        $this->post('/checkout')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $customer->email, 'password' => 'CustomerPass123'])
            ->assertRedirect(route('checkout.create'));
        $this->get('/checkout')->assertOk()->assertSee('Delivery information')->assertSee($customer->email);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => Cart::where('user_id', $customer->id)->value('id'),
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_authenticated_checkout_creates_snapshots_and_uses_only_server_values(): void
    {
        $product = $this->makeProduct(price: '100.50', stock: 5, slug: 'server-priced-item');
        $customer = $this->makeCustomer('checkout@example.com', address: '22 Test Road');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $this->assertSame(5, (int) $product->fresh()->stock_quantity); // Cart adds do not reserve inventory.

        $product->update(['name' => 'Current DB Name', 'price' => '125.25', 'sku' => 'CURRENT-SKU']);
        $token = $this->openCheckout();
        $response = $this->post('/checkout', $this->checkoutPayload($token, [
            'price' => '0.01',
            'subtotal' => 1,
            'subtotal_minor' => 1,
            'shipping_amount' => 0,
            'shipping_amount_minor' => 0,
            'discount_amount' => 999999,
            'total_amount' => 1,
            'user_id' => 999999,
            'payment_method_name' => 'PayPal',
            'payment_status' => 'verified',
            'order_status' => 'delivered',
        ]));

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('orders.success', $order));
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6,}$/', $order->order_number);
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame(25050, $order->subtotal_minor); // 125.25 x 2, in paisa.
        $this->assertSame(25000, $order->shipping_amount_minor);
        $this->assertSame(0, $order->discount_amount_minor);
        $this->assertSame(50050, $order->total_amount_minor);
        $this->assertSame('PKR', $order->currency);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame($this->paymentMethod->id, $order->payment_method_id);
        $this->assertSame('Customer One', $order->customer_name);
        $this->assertSame('checkout@example.com', $order->customer_email);
        $this->assertSame('22 Test Road', $order->shipping_address);

        $orderItem = $order->items()->firstOrFail();
        $this->assertSame('Current DB Name', $orderItem->product_name);
        $this->assertSame('CURRENT-SKU', $orderItem->product_sku);
        $this->assertSame(12525, $orderItem->unit_price_minor);
        $this->assertSame(2, $orderItem->quantity);
        $this->assertSame(25050, $orderItem->line_total_minor);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertSame(0, $customer->cart->items()->count());

        $this->get(route('orders.success', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Current DB Name')
            ->assertSee('Pending');

        $product->update(['name' => 'Renamed Later', 'price' => '999.99']);
        $this->assertSame('Current DB Name', $orderItem->fresh()->product_name);
        $this->assertSame(12525, $orderItem->fresh()->unit_price_minor);
        $product->delete();
        $this->assertNull($orderItem->fresh()->product_id);
        $this->get(route('orders.success', $order))->assertOk()->assertSee('Current DB Name')->assertSee('125.25');
    }

    public function test_shipping_threshold_is_configurable_and_calculated_server_side(): void
    {
        config(['checkout.shipping.standard_fee_minor' => 8000, 'checkout.shipping.free_threshold_minor' => 20000]);
        $product = $this->makeProduct(price: '100.00', stock: 4, slug: 'free-shipping-threshold-item');
        $customer = $this->makeCustomer('free-shipping@example.com');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();

        $token = $this->openCheckout();
        $this->post('/checkout', $this->checkoutPayload($token, ['shipping_amount_minor' => 990000]))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(20000, $order->subtotal_minor);
        $this->assertSame(0, $order->shipping_amount_minor);
        $this->assertSame(20000, $order->total_amount_minor);
    }

    public function test_empty_cart_cannot_create_an_order(): void
    {
        $customer = $this->makeCustomer('empty-checkout@example.com');
        $this->actingAs($customer, 'web');
        $this->get('/checkout')->assertRedirect(route('cart'));

        $token = (string) \Illuminate\Support\Str::uuid();
        session(['checkout.idempotency_token' => $token]);
        $this->from('/checkout')->post('/checkout', $this->checkoutPayload($token))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_inactive_product_prevents_checkout_and_preserves_cart_and_stock(): void
    {
        $product = $this->makeProduct(stock: 5, slug: 'inactive-at-checkout');
        $customer = $this->makeCustomer('inactive-checkout@example.com');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $product->update(['is_active' => false]);
        $token = $this->openCheckout();

        $this->from('/checkout')->post('/checkout', $this->checkoutPayload($token))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(2, Cart::where('user_id', $customer->id)->firstOrFail()->items()->firstOrFail()->quantity);
        $this->assertSame(5, (int) $product->fresh()->stock_quantity);
    }

    public function test_insufficient_stock_prevents_order_creation_and_identifies_the_product(): void
    {
        $product = $this->makeProduct(stock: 5, slug: 'stock-changed-after-cart');
        $customer = $this->makeCustomer('stock-changed@example.com');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 3])->assertCreated();
        $product->update(['stock_quantity' => 2]);
        $token = $this->openCheckout();

        $this->from('/checkout')->post('/checkout', $this->checkoutPayload($token))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('cart');
        $this->assertStringContainsString($product->name, session('errors')->first('cart'));
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(3, Cart::where('user_id', $customer->id)->firstOrFail()->items()->firstOrFail()->quantity);
        $this->assertSame(2, (int) $product->fresh()->stock_quantity);
    }

    public function test_repeated_submission_with_same_checkout_token_returns_the_same_order(): void
    {
        $product = $this->makeProduct(stock: 5, slug: 'idempotent-checkout-item');
        $customer = $this->makeCustomer('idempotent@example.com');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $token = $this->openCheckout();
        $payload = $this->checkoutPayload($token);

        $this->post('/checkout', $payload)->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->post('/checkout', $payload)->assertRedirect(route('orders.success', $order));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, OrderItem::query()->count());
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
    }

    public function test_order_number_is_unique_across_orders(): void
    {
        $customer = $this->makeCustomer('number-one@example.com');
        $firstProduct = $this->makeProduct(slug: 'number-first-item');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $firstProduct->slug, 'quantity' => 1])->assertCreated();
        $this->post('/checkout', $this->checkoutPayload($this->openCheckout()))->assertRedirect();
        $firstOrder = Order::query()->firstOrFail();

        $this->postJson('/cart/add', ['product' => $this->makeProduct(slug: 'number-second-item')->slug, 'quantity' => 1])->assertCreated();
        $this->post('/checkout', $this->checkoutPayload($this->openCheckout()))->assertRedirect();
        $orders = Order::query()->orderBy('id')->get();

        $this->assertCount(2, $orders);
        $this->assertNotSame($firstOrder->order_number, $orders[1]->order_number);
        $this->assertSame(2, $orders->pluck('order_number')->unique()->count());
    }

    public function test_failed_order_transaction_rolls_back_order_items_stock_and_cart_changes(): void
    {
        $firstProduct = $this->makeProduct(stock: 5, slug: 'rollback-first');
        $secondProduct = $this->makeProduct(stock: 5, slug: 'rollback-second');
        $customer = $this->makeCustomer('rollback@example.com');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $firstProduct->slug, 'quantity' => 1])->assertCreated();
        $this->postJson('/cart/add', ['product' => $secondProduct->slug, 'quantity' => 1])->assertCreated();
        $token = $this->openCheckout();

        $productUpdates = 0;
        DB::listen(function (QueryExecuted $query) use (&$productUpdates): void {
            if (preg_match('/^\s*update\s+["`]?products["`]?/i', $query->sql)) {
                $productUpdates++;
                if ($productUpdates === 1) {
                    throw new \RuntimeException('Forced failure after the first stock decrement.');
                }
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->post('/checkout', $this->checkoutPayload($token));
            $this->fail('The injected transactional failure was not raised.');
        } catch (Throwable $exception) {
            $this->assertSame('Forced failure after the first stock decrement.', $exception->getMessage());
        }
        $this->withExceptionHandling();

        $this->assertGreaterThanOrEqual(1, $productUpdates);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(5, (int) $firstProduct->fresh()->stock_quantity);
        $this->assertSame(5, (int) $secondProduct->fresh()->stock_quantity);
        $this->assertSame(2, Cart::where('user_id', $customer->id)->firstOrFail()->items()->count());
    }

    public function test_customer_can_view_own_orders_but_not_another_customers_order(): void
    {
        $product = $this->makeProduct(slug: 'private-order-item');
        $owner = $this->makeCustomer('order-owner@example.com');
        $this->actingAs($owner, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->post('/checkout', $this->checkoutPayload($this->openCheckout()))->assertRedirect();
        $order = Order::query()->firstOrFail();

        $this->get('/account/orders')->assertOk()->assertSee($order->order_number);
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee($product->name);

        $other = $this->makeCustomer('order-other@example.com');
        $this->actingAs($other, 'web')->get(route('account.orders.show', $order))->assertForbidden();
        $this->get(route('orders.success', $order))->assertForbidden();
    }

    public function test_admin_order_routes_are_protected_and_admin_can_filter_view_and_update_status(): void
    {
        $product = $this->makeProduct(slug: 'admin-order-item');
        $customer = $this->makeCustomer('admin-order-customer@example.com');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
        $this->post('/checkout', $this->checkoutPayload($this->openCheckout()))->assertRedirect();
        $order = Order::query()->firstOrFail();

        $this->get('/admin/orders')->assertRedirect(route('admin.login'));
        $this->actingAs($customer, 'web')->get('/admin/orders')->assertRedirect(route('admin.login'));

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')
            ->get('/admin/orders?order_number='.$order->order_number.'&status=pending')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($customer->email);
        $this->get('/admin/orders?customer='.$customer->email.'&from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertSee($order->order_number);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Update order status');

        $this->patch(route('admin.orders.status.update', $order), [
            'order_status' => 'confirmed',
            'payment_status' => 'verified',
        ])->assertRedirect();
        $order->refresh();
        $this->assertSame('confirmed', $order->order_status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotNull($order->confirmed_at);

        $this->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.status.update', $order), ['order_status' => 'delivered'])
            ->assertSessionHasErrors('order_status');
        $this->assertSame('confirmed', $order->fresh()->order_status);

        $this->patch(route('admin.orders.status.update', $order), ['order_status' => 'processing'])->assertRedirect();
        $this->patch(route('admin.orders.status.update', $order), [
            'order_status' => 'shipped',
            'tracking_number' => 'PK-TRACK-001',
        ])->assertRedirect();
        $this->assertSame('shipped', $order->fresh()->order_status);
        $this->assertSame('PK-TRACK-001', $order->fresh()->tracking_number);
        $this->assertNotNull($order->fresh()->shipped_at);
    }

    public function test_cancelling_an_order_restores_stock_once(): void
    {
        $product = $this->makeProduct(stock: 4, slug: 'cancel-restock-item');
        $customer = $this->makeCustomer('cancel-restock@example.com');
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 2])->assertCreated();
        $this->post('/checkout', $this->checkoutPayload($this->openCheckout()))->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame(2, (int) $product->fresh()->stock_quantity);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->patch(route('admin.orders.status.update', $order), ['order_status' => 'cancelled'])->assertRedirect();
        $this->assertSame(4, (int) $product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);

        $this->patch(route('admin.orders.status.update', $order), ['order_status' => 'cancelled'])->assertRedirect();
        $this->assertSame(4, (int) $product->fresh()->stock_quantity);
    }

    private function openCheckout(): string
    {
        $this->get('/checkout')->assertOk();
        $token = $this->app['session.store']->get('checkout.idempotency_token');
        $this->assertIsString($token);

        return $token;
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'checkout_token' => $token,
            'payment_method_id' => $this->paymentMethod->id,
            'customer_name' => 'Customer One',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '03001234567',
            'shipping_address' => '100 Commerce Street',
            'city' => 'Karachi',
            'province' => 'Sindh',
            'postal_code' => '74000',
            'customer_notes' => 'Call on arrival.',
        ], $overrides);
    }

    private function makeProduct(string|int $price = '100.00', int $stock = 10, string $slug = 'checkout-product'): Product
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'checkout-category'],
            ['name' => 'Checkout category', 'is_active' => true]
        );
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'checkout-brand'],
            ['name' => 'Checkout brand', 'is_active' => true]
        );

        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Product '.str_replace('-', ' ', $slug),
            'slug' => $slug,
            'sku' => strtoupper(substr(str_replace('-', '', $slug), 0, 60)),
            'price' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
            'icon' => 'devices_other',
        ]);
    }

    private function makeCustomer(string $email, string $address = 'Karachi, Sindh'): User
    {
        return User::query()->create([
            'name' => 'Customer One',
            'email' => $email,
            'phone' => '03001234567',
            'address' => $address,
            'password' => 'CustomerPass123',
        ]);
    }

    private function makeAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Order Admin',
            'email' => 'admin-orders@example.com',
            'password' => 'AdminPass123',
        ]);
    }
}
