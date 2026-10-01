<?php

namespace App\Services\Checkout;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Coupons\CouponService;
use App\Services\Settings\StoreSettingsService;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OverflowException;

class OrderService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly ShippingCalculator $shipping,
        private readonly CouponService $coupons,
        private readonly StoreSettingsService $settings
    ) {
    }

    /**
     * Create a read-only, current-price checkout preview. Final order creation always recalculates
     * this data under transaction and product row locks.
     *
     * @return array{items: array, subtotal_minor: int, shipping_minor: int, discount_minor: int, total_minor: int, coupon: ?\\App\\Models\\Coupon, coupon_code: ?string, coupon_error: ?string, issues: array}
     */
    public function preview(User $user, ?string $couponCode = null): array
    {
        $cart = $this->carts->current($user, '');
        $items = $this->carts->items($cart);

        return $this->quoteItems($items, $user, $couponCode);
    }

    public function findByIdempotencyKey(User $user, string $key): ?Order
    {
        return Order::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * @param array{name: string, email: string, phone: string, address: string, city: string, province: string, postal_code: string, notes: ?string} $customer
     */
    public function createFromCart(
        User $user,
        array $customer,
        string $idempotencyKey,
        int $paymentMethodId,
        ?string $couponCode = null
    ): Order {
        return DB::transaction(function () use ($user, $customer, $idempotencyKey, $paymentMethodId, $couponCode): Order {
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $user->getKey(), 404);

                return $existing->load('items');
            }

            // CartService remains the source of the authenticated customer's current cart.
            $cart = $this->carts->lockCurrent($user, '');
            if (! $cart) {
                throw ValidationException::withMessages(['cart' => 'Your cart is no longer available. Please review it and try again.']);
            }

            // A concurrent replay waits on the cart lock, then returns the one order created by its peer.
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $user->getKey(), 404);

                return $existing->load('items');
            }

            $paymentMethod = PaymentMethod::query()
                ->whereKey($paymentMethodId)
                ->where('is_active', true)
                ->whereIn('slug', array_keys(PaymentMethod::AVAILABLE_METHODS))
                ->lockForUpdate()
                ->first();

            if (! $paymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'That payment method is no longer available. Please choose an active method.',
                ]);
            }

            $cartItems = $this->carts->items($cart, lockForUpdate: true);

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty. Add products before placing an order.']);
            }

            $productIds = $cartItems->pluck('product_id')->unique()->sort()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cartItems as $cartItem) {
                $cartItem->setRelation('product', $products->get($cartItem->product_id));
            }

            $quote = $this->quoteItems($cartItems, $user, $couponCode, lockCoupon: true);
            if ($quote['issues'] !== []) {
                throw ValidationException::withMessages(['cart' => implode(' ', $quote['issues'])]);
            }
            if ($quote['coupon_error'] !== null) {
                throw ValidationException::withMessages(['coupon_code' => $quote['coupon_error']]);
            }

            $order = Order::query()->create([
                'user_id' => $user->getKey(),
                'order_number' => 'TMP-'.Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'shipping_address' => $customer['address'],
                'city' => $customer['city'],
                'province' => $customer['province'],
                'postal_code' => $customer['postal_code'],
                'customer_notes' => $customer['notes'] ?: null,
                'subtotal_minor' => $quote['subtotal_minor'],
                'shipping_amount_minor' => $quote['shipping_minor'],
                'discount_amount_minor' => $quote['discount_minor'],
                'total_amount_minor' => $quote['total_minor'],
                'currency' => $this->settings->currency(),
                'payment_method_id' => $paymentMethod->getKey(),
                'coupon_id' => $quote['coupon']?->getKey(),
                'coupon_code' => $quote['coupon_code'],
                'payment_status' => 'pending',
                'order_status' => 'pending',
            ]);

            $order->order_number = sprintf('ORD-%s-%06d', now()->format('Y'), $order->getKey());
            $order->save();

            foreach ($quote['items'] as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $order->items()->create([
                    'product_id' => $product->getKey(),
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price_minor' => $line['unit_price_minor'],
                    'quantity' => $line['quantity'],
                    'line_total_minor' => $line['line_total_minor'],
                ]);
            }

            // The locked rows are rechecked in the UPDATE predicate as an additional no-oversell guard.
            foreach ($quote['items'] as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $decremented = Product::query()
                    ->whereKey($product->getKey())
                    ->where('is_active', true)
                    ->where('stock_quantity', '>=', $line['quantity'])
                    ->decrement('stock_quantity', $line['quantity']);

                if ($decremented !== 1) {
                    $available = (int) Product::query()->whereKey($product->getKey())->value('stock_quantity');
                    throw ValidationException::withMessages([
                        'cart' => $product->name.' stock changed during checkout; only '.$available.' unit(s) remain. Please review your cart.',
                    ]);
                }
            }

            $this->carts->clearItems($cart);

            if ($quote['coupon']) {
                $this->coupons->recordUsage($quote['coupon'], $user, $order, $quote['discount_minor']);
            }

            return $order->load(['items', 'coupon']);
        }, 3);
    }

    /** @param iterable<CartItem> $cartItems */
    private function quoteItems(iterable $cartItems, User $user, ?string $couponCode, bool $lockCoupon = false): array
    {
        $lines = [];
        $issues = [];
        $subtotalMinor = 0;

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;
            if (! $product) {
                $issues[] = 'A product in your cart is no longer available; remove it before checkout.';
                continue;
            }

            if ((int) $cartItem->quantity < 1) {
                $issues[] = $product->name.' has an invalid cart quantity; update or remove it before checkout.';
                continue;
            }

            if (! $product->is_active) {
                $issues[] = $product->name.' is no longer active; remove it before checkout.';
            } elseif ($product->stock_quantity < 1) {
                $issues[] = $product->name.' is out of stock; remove it before checkout.';
            } elseif ($cartItem->quantity > $product->stock_quantity) {
                $issues[] = $product->name.' has only '.$product->stock_quantity.' unit(s) available, but '.$cartItem->quantity.' are in your cart.';
            }

            try {
                $unitPriceMinor = Money::toMinor((string) $product->price);
                $lineTotalMinor = Money::multiplyMinor($unitPriceMinor, (int) $cartItem->quantity);
                $subtotalMinor = Money::addMinor($subtotalMinor, $lineTotalMinor);
            } catch (OverflowException) {
                $issues[] = $product->name.' has a price or quantity outside the supported order total range.';
                $unitPriceMinor = 0;
                $lineTotalMinor = 0;
            }

            $lines[] = [
                'product' => $product,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => (int) $cartItem->quantity,
                'unit_price_minor' => $unitPriceMinor,
                'line_total_minor' => $lineTotalMinor,
            ];
        }

        $couponQuote = $this->coupons->quote($couponCode, $user, $subtotalMinor, $lockCoupon);
        $discountMinor = $couponQuote['discount_minor'];
        $minimumOrderMinor = $this->settings->minimumOrderAmountMinor();
        if ($subtotalMinor < $minimumOrderMinor) {
            $issues[] = 'The current cart does not meet the minimum order amount of '.$this->settings->currency().' '.Money::formatMinor($minimumOrderMinor).'.';
        }

        try {
            $shippingMinor = $this->shipping->calculate($subtotalMinor);
            $payableSubtotalMinor = $subtotalMinor - $discountMinor;
            $totalMinor = Money::addMinor($payableSubtotalMinor, $shippingMinor);
        } catch (OverflowException) {
            $issues[] = 'Your order total exceeds the supported range. Please contact customer support.';
            $subtotalMinor = 0;
            $shippingMinor = 0;
            $discountMinor = 0;
            $totalMinor = 0;
        }

        return [
            'items' => $lines,
            'subtotal_minor' => $subtotalMinor,
            'shipping_minor' => $shippingMinor,
            'discount_minor' => $discountMinor,
            'total_minor' => $totalMinor,
            'coupon' => $couponQuote['coupon'],
            'coupon_code' => $couponQuote['code'],
            'coupon_error' => $couponQuote['error'],
            'issues' => array_values(array_unique($issues)),
        ];
    }
}
