<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('payment-proofs');
        config([
            'checkout.shipping.standard_fee_minor' => 0,
            'checkout.shipping.free_threshold_minor' => 999999999,
        ]);
    }

    public function test_inactive_payment_methods_are_not_selectable_or_accepted_at_checkout(): void
    {
        $customer = $this->makeCustomer('inactive-method@example.com');
        $method = $this->makeMethod(active: false);
        $this->addCartItem($customer);
        $this->actingAs($customer, 'web')->get('/checkout')->assertOk()->assertDontSee($method->name);
        $token = (string) $this->app['session.store']->get('checkout.idempotency_token');

        $this->from('/checkout')->post('/checkout', $this->checkoutPayload($token, $method->id))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('payment_method_id');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_active_payment_method_is_stored_and_details_and_server_total_are_shown(): void
    {
        $customer = $this->makeCustomer('active-method@example.com');
        $method = $this->makeMethod(active: true, accountNumber: 'BA-889900');
        $order = $this->createOrder($customer, $method, [
            'total_amount_minor' => 1,
            'payment_status' => 'verified',
            'payment_method_name' => 'PayPal',
        ]);

        $this->assertSame($method->id, $order->payment_method_id);
        $this->assertSame(10000, $order->total_amount_minor);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->order_status);
        $this->get(route('orders.success', $order))
            ->assertOk()
            ->assertSee('Bank Alfalah')
            ->assertSee('MUZORA Settlement Account')
            ->assertSee('BA-889900')
            ->assertSee('PKR 100.00');
    }

    public function test_customer_can_submit_a_valid_image_proof_and_order_payment_becomes_submitted(): void
    {
        [$customer, $order] = $this->makeOrder();

        $this->actingAs($customer, 'web')->post(route('account.orders.payment-proof.store', $order), [
            'transaction_id' => 'TXN-2026-4488',
            'notes' => 'Transferred from my bank app.',
            'screenshot' => $this->validPng(),
        ])->assertRedirect(route('account.orders.show', $order));

        $proof = PaymentProof::query()->firstOrFail();
        $this->assertSame($order->id, $proof->order_id);
        $this->assertSame($customer->id, $proof->user_id);
        $this->assertSame('TXN-2026-4488', $proof->transaction_id);
        $this->assertSame(10000, $proof->amount_minor);
        $this->assertSame('submitted', $proof->status);
        $this->assertNotNull($proof->submitted_at);
        $this->assertStringStartsWith('orders/'.$order->id.'/', $proof->screenshot_path);
        Storage::disk('payment-proofs')->assertExists($proof->screenshot_path);
        $this->assertSame('submitted', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->order_status);
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('TXN-2026-4488')->assertSee('waiting for administrator review');
    }

    public function test_unauthenticated_customer_cannot_submit_payment_proof(): void
    {
        [, $order] = $this->makeOrder();
        auth('web')->logout();
        $this->post(route('account.orders.payment-proof.store', $order), [
            'screenshot' => $this->validPng(),
        ])->assertRedirect(route('login'));
    }

    public function test_customer_cannot_submit_or_view_another_customers_payment_proof(): void
    {
        [$owner, $order] = $this->makeOrder();
        $proof = $this->submitProof($owner, $order);
        $other = $this->makeCustomer('payment-other@example.com');

        $this->actingAs($other, 'web')
            ->post(route('account.orders.payment-proof.store', $order), ['screenshot' => $this->validPng()])
            ->assertForbidden();
        $this->get(route('account.orders.payment-proofs.download', [$order, $proof]))->assertForbidden();
        $this->assertSame(1, PaymentProof::query()->count());
    }

    public function test_upload_rejects_non_image_and_dangerous_script_file(): void
    {
        [$customer, $order] = $this->makeOrder();
        $this->actingAs($customer, 'web');

        $this->from(route('account.orders.show', $order))->post(route('account.orders.payment-proof.store', $order), [
            'screenshot' => UploadedFile::fake()->create('shell.php', 12, 'application/x-php'),
        ])->assertRedirect(route('account.orders.show', $order))->assertSessionHasErrors('screenshot');

        $this->assertSame(0, PaymentProof::query()->count());
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame([], Storage::disk('payment-proofs')->allFiles());
    }

    public function test_transaction_id_is_validated_and_markup_is_rejected(): void
    {
        [$customer, $order] = $this->makeOrder();
        $this->actingAs($customer, 'web')->from(route('account.orders.show', $order))
            ->post(route('account.orders.payment-proof.store', $order), [
                'transaction_id' => '<script>alert(1)</script>',
                'screenshot' => $this->validPng(),
            ])->assertRedirect(route('account.orders.show', $order))->assertSessionHasErrors('transaction_id');

        $this->assertSame(0, PaymentProof::query()->count());
    }

    public function test_customer_cannot_set_payment_status_to_verified_or_submit_an_amount(): void
    {
        [$customer, $order] = $this->makeOrder();
        $this->actingAs($customer, 'web')->post(route('account.orders.payment-proof.store', $order), [
            'payment_status' => 'verified',
            'amount_minor' => 1,
            'screenshot' => $this->validPng(),
        ])->assertRedirect();

        $proof = PaymentProof::query()->firstOrFail();
        $this->assertSame(10000, $proof->amount_minor);
        $this->assertSame('submitted', $proof->status);
        $this->assertSame('submitted', $order->fresh()->payment_status);
    }

    public function test_customer_cannot_replace_or_change_verified_payment_proof(): void
    {
        [$customer, $order] = $this->makeOrder();
        $proof = $this->submitProof($customer, $order);
        $proof->update(['status' => 'verified']);
        $order->update(['payment_status' => 'verified']);

        $this->actingAs($customer, 'web')->from(route('account.orders.show', $order))
            ->post(route('account.orders.payment-proof.store', $order), ['screenshot' => $this->validPng()])
            ->assertRedirect(route('account.orders.show', $order))
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(1, PaymentProof::query()->count());
        $this->assertSame('verified', $order->fresh()->payment_status);
    }

    public function test_rejected_payment_can_be_resubmitted_as_a_new_proof_attempt(): void
    {
        [$customer, $order] = $this->makeOrder();
        $first = $this->submitProof($customer, $order, 'TXN-OLD');
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->patch(route('admin.payments.review', $order), [
            'decision' => 'rejected',
            'rejection_reason' => 'The transfer reference is not readable.',
        ])->assertRedirect();
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('rejected', $order->fresh()->payment_status);

        $this->actingAs($customer, 'web')->post(route('account.orders.payment-proof.store', $order), [
            'transaction_id' => 'TXN-CORRECTED',
            'screenshot' => $this->validPng(),
        ])->assertRedirect();

        $this->assertSame(2, PaymentProof::query()->count());
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('submitted', $order->fresh()->payment_status);
        $this->assertSame('TXN-CORRECTED', $order->fresh()->paymentProofs()->firstOrFail()->transaction_id);
    }

    public function test_duplicate_proof_submission_is_blocked_while_review_is_pending(): void
    {
        [$customer, $order] = $this->makeOrder();
        $this->submitProof($customer, $order, 'TXN-ONE');
        $this->actingAs($customer, 'web')->from(route('account.orders.show', $order))
            ->post(route('account.orders.payment-proof.store', $order), [
                'transaction_id' => 'TXN-TWO',
                'screenshot' => $this->validPng(),
            ])->assertRedirect(route('account.orders.show', $order))->assertSessionHasErrors('payment_proof');

        $this->assertSame(1, PaymentProof::query()->count());
        $this->assertCount(1, Storage::disk('payment-proofs')->allFiles());
    }

    public function test_admin_payment_queue_shows_only_submitted_payments(): void
    {
        [$customer, $order] = $this->makeOrder();
        $this->submitProof($customer, $order, 'TXN-QUEUE');
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->get('/admin/payments')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Bank Alfalah')
            ->assertSee('100.00');
    }

    public function test_inactive_admin_cannot_review_a_payment(): void
    {
        [$customer, $order] = $this->makeOrder();
        $this->submitProof($customer, $order);
        $admin = $this->makeAdmin(active: false);

        $this->actingAs($admin, 'admin')->get('/admin/payments')->assertRedirect(route('admin.login'));
        $this->assertSame('submitted', $order->fresh()->payment_status);
    }

    public function test_active_admin_can_verify_without_changing_fulfillment_status(): void
    {
        [$customer, $order] = $this->makeOrder();
        $proof = $this->submitProof($customer, $order, 'TXN-VERIFY');
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->patch(route('admin.payments.review', $order), ['decision' => 'verified'])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('verified', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame('verified', $proof->fresh()->status);
        $this->assertSame($admin->id, $proof->fresh()->reviewed_by);
        $this->assertNotNull($proof->fresh()->reviewed_at);
    }

    public function test_active_admin_can_reject_payment_and_customer_sees_reason(): void
    {
        [$customer, $order] = $this->makeOrder();
        $proof = $this->submitProof($customer, $order);
        $admin = $this->makeAdmin();
        $reason = 'Please upload a clearer transaction receipt.';

        $this->actingAs($admin, 'admin')->patch(route('admin.payments.review', $order), [
            'decision' => 'rejected',
            'rejection_reason' => $reason,
        ])->assertRedirect();

        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertSame($reason, $proof->fresh()->rejection_reason);
        $this->actingAs($customer, 'web')->get(route('account.orders.show', $order))->assertOk()->assertSee($reason);
    }

    public function test_admin_must_supply_a_rejection_reason(): void
    {
        [, $order] = $this->makeOrder();
        $this->submitProof($order->user, $order);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->from(route('admin.payments.show', $order))
            ->patch(route('admin.payments.review', $order), ['decision' => 'rejected'])
            ->assertRedirect(route('admin.payments.show', $order))
            ->assertSessionHasErrors('rejection_reason');
        $this->assertSame('submitted', $order->fresh()->payment_status);
    }

    public function test_customer_proof_download_is_private_and_admin_can_download_after_authorization(): void
    {
        [$owner, $order] = $this->makeOrder();
        $proof = $this->submitProof($owner, $order);
        $other = $this->makeCustomer('proof-download-other@example.com');

        auth('web')->logout();
        $this->get(route('account.orders.payment-proofs.download', [$order, $proof]))->assertRedirect(route('login'));
        $this->actingAs($other, 'web')->get(route('account.orders.payment-proofs.download', [$order, $proof]))->assertForbidden();

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'admin')->get(route('admin.payments.proofs.download', [$order, $proof]));
        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_customer_order_details_show_database_payment_instructions(): void
    {
        $customer = $this->makeCustomer('instructions@example.com');
        $method = $this->makeMethod(active: true, accountNumber: 'MEZ-12345');
        $method->update(['instructions' => 'Use your order number as the bank transfer reference.']);
        $order = $this->createOrder($customer, $method);

        $this->actingAs($customer, 'web')->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee($method->name)
            ->assertSee('MEZ-12345')
            ->assertSee('Use your order number as the bank transfer reference.');
    }

    public function test_payment_method_admin_crud_is_guarded_and_limited_to_the_four_supported_options(): void
    {
        $this->get('/admin/payment-methods')->assertRedirect(route('admin.login'));
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->get('/admin/payment-methods/create')->assertOk()->assertSee('Bank Alfalah')->assertSee('JazzCash')->assertDontSee('PayPal');

        $this->post('/admin/payment-methods', [
            'method' => 'easypaisa',
            'mobile_number' => '+923001112233',
            'instructions' => 'Send funds to this wallet.',
            'is_active' => '1',
            'sort_order' => 5,
        ])->assertRedirect(route('admin.payment-methods.index'));
        $method = PaymentMethod::query()->firstOrFail();
        $this->assertSame('EasyPaisa', $method->name);
        $this->assertSame('mobile_wallet', $method->type);
        $this->assertTrue($method->is_active);

        $this->put(route('admin.payment-methods.update', $method), [
            'mobile_number' => '+923009998877',
            'instructions' => 'Updated wallet instructions.',
            'is_active' => '0',
            'sort_order' => 10,
        ])->assertRedirect(route('admin.payment-methods.index'));
        $this->assertFalse($method->fresh()->is_active);
        $this->assertSame('+923009998877', $method->fresh()->mobile_number);

        $this->from('/admin/payment-methods/create')->post('/admin/payment-methods', [
            'method' => 'paypal',
            'is_active' => '0',
            'sort_order' => 0,
        ])->assertRedirect('/admin/payment-methods/create')->assertSessionHasErrors('method');
    }

    public function test_active_payment_method_cannot_be_activated_without_required_transfer_details(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->from('/admin/payment-methods/create')
            ->post('/admin/payment-methods', [
                'method' => 'bank-alfalah',
                'is_active' => '1',
                'sort_order' => 0,
            ])->assertRedirect('/admin/payment-methods/create')
            ->assertSessionHasErrors(['account_title', 'account_number']);

        $this->assertSame(0, PaymentMethod::query()->count());
    }

    public function test_upload_rejects_non_image_content_even_when_the_filename_uses_an_image_extension(): void
    {
        [$customer, $order] = $this->makeOrder();
        $this->actingAs($customer, 'web')->from(route('account.orders.show', $order))
            ->post(route('account.orders.payment-proof.store', $order), [
                'screenshot' => UploadedFile::fake()->createWithContent('receipt.png', '<?php echo 1;'),
            ])->assertRedirect(route('account.orders.show', $order))->assertSessionHasErrors('screenshot');

        $this->assertSame(0, PaymentProof::query()->count());
        $this->assertSame([], Storage::disk('payment-proofs')->allFiles());
    }

    public function test_owner_can_download_their_private_payment_proof(): void
    {
        [$customer, $order] = $this->makeOrder();
        $proof = $this->submitProof($customer, $order);

        $response = $this->actingAs($customer, 'web')->get(route('account.orders.payment-proofs.download', [$order, $proof]));
        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    public function test_unconfigured_methods_do_not_break_checkout_and_checkout_requires_a_selection(): void
    {
        $customer = $this->makeCustomer('no-methods@example.com');
        $this->addCartItem($customer);
        $this->actingAs($customer, 'web')->get('/checkout')->assertOk()->assertSee('not configured yet');
        $token = (string) $this->app['session.store']->get('checkout.idempotency_token');
        $this->from('/checkout')->post('/checkout', array_diff_key($this->checkoutPayload($token, 999), ['payment_method_id' => true]))
            ->assertRedirect('/checkout')->assertSessionHasErrors('payment_method_id');
        $this->assertSame(0, Order::query()->count());
    }

    /** @return array{0: User, 1: Order} */
    private function makeOrder(): array
    {
        $customer = $this->makeCustomer('buyer-'.uniqid().'@example.com');
        $method = $this->makeMethod(active: true);

        return [$customer, $this->createOrder($customer, $method)];
    }

    private function createOrder(User $customer, PaymentMethod $method, array $tampered = []): Order
    {
        $this->addCartItem($customer);
        $this->actingAs($customer, 'web')->get('/checkout')->assertOk();
        $token = (string) $this->app['session.store']->get('checkout.idempotency_token');
        $this->post('/checkout', array_merge($this->checkoutPayload($token, $method->id), $tampered))->assertRedirect();

        return Order::query()->firstOrFail();
    }

    private function addCartItem(User $customer): void
    {
        $category = Category::query()->create(['name' => 'Payment category', 'slug' => 'payment-category', 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Payment brand', 'slug' => 'payment-brand', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Payment test product',
            'slug' => 'payment-item-'.uniqid(),
            'sku' => 'PAY-'.strtoupper(substr(uniqid(), -8)),
            'price' => '100.00',
            'stock_quantity' => 5,
            'is_active' => true,
            'icon' => 'devices_other',
        ]);
        $this->actingAs($customer, 'web')->postJson('/cart/add', ['product' => $product->slug, 'quantity' => 1])->assertCreated();
    }

    private function makeMethod(bool $active, string $slug = 'bank-alfalah', string $accountNumber = 'BA-123456'): PaymentMethod
    {
        $method = PaymentMethod::AVAILABLE_METHODS[$slug];

        return PaymentMethod::query()->create([
            'name' => $method['name'],
            'slug' => $slug,
            'type' => $method['type'],
            'account_title' => $method['type'] === 'bank' ? 'MUZORA Settlement Account' : null,
            'account_number' => $method['type'] === 'bank' ? $accountNumber : null,
            'iban' => null,
            'mobile_number' => $method['type'] === 'mobile_wallet' ? '+923001234567' : null,
            'instructions' => 'Transfer the exact order total.',
            'is_active' => $active,
            'sort_order' => 1,
        ]);
    }

    private function makeCustomer(string $email): User
    {
        return User::query()->create([
            'name' => 'Payment Customer',
            'email' => $email,
            'phone' => '03001234567',
            'address' => 'Payment Street, Karachi',
            'password' => 'CustomerPass123',
        ]);
    }

    private function makeAdmin(bool $active = true): Admin
    {
        $admin = Admin::query()->create([
            'name' => 'Payment Admin',
            'email' => 'payment-admin-'.uniqid().'@example.com',
            'password' => 'AdminPass123',
        ]);
        if (! $active) {
            $admin->forceFill(['is_active' => false])->save();
        }

        return $admin;
    }

    private function submitProof(User $customer, Order $order, ?string $transactionId = null): PaymentProof
    {
        $payload = ['screenshot' => $this->validPng()];
        if ($transactionId !== null) {
            $payload['transaction_id'] = $transactionId;
        }

        $this->actingAs($customer, 'web')->post(route('account.orders.payment-proof.store', $order), $payload)->assertRedirect();

        return $order->paymentProofs()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(string $token, int $methodId): array
    {
        return [
            'checkout_token' => $token,
            'payment_method_id' => $methodId,
            'customer_name' => 'Payment Customer',
            'customer_email' => 'payment-buyer@example.com',
            'customer_phone' => '03001234567',
            'shipping_address' => 'Payment Street, Karachi',
            'city' => 'Karachi',
            'province' => 'Sindh',
            'postal_code' => '74000',
            'customer_notes' => '',
        ];
    }

    private function validPng(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGP4DwQACfsD/fteaysAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent('receipt.png', $png ?: 'not a png');
    }
}
