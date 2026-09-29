<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OverflowException;

class CartService
{
    public function current(?User $user, string $sessionId): Cart
    {
        if ($user) {
            return $user->cart()->firstOrCreate([], ['session_id' => null]);
        }

        return Cart::query()->firstOrCreate(
            ['session_id' => $this->guestSessionKey($sessionId)],
            ['user_id' => null]
        );
    }

    public function findCurrent(?User $user, string $sessionId): ?Cart
    {
        if ($user) {
            return $user->cart()->first();
        }

        return Cart::query()->where('session_id', $this->guestSessionKey($sessionId))->first();
    }

    public function lockCurrent(?User $user, string $sessionId): ?Cart
    {
        $cart = $this->findCurrent($user, $sessionId);

        return $cart ? Cart::query()->lockForUpdate()->find($cart->getKey()) : null;
    }

    public function countCurrent(?User $user, string $sessionId): int
    {
        $cart = $this->findCurrent($user, $sessionId);

        return $cart ? (int) $cart->items()->sum('quantity') : 0;
    }

    /** Retrieve the current persisted line items; checkout may request locks inside its transaction. */
    public function items(Cart $cart, bool $lockForUpdate = false): Collection
    {
        $query = $cart->items()->with('product')->orderBy('product_id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function add(?User $user, string $sessionId, Product $product, int $quantity): CartItem
    {
        $this->validateQuantity($quantity);

        return DB::transaction(function () use ($user, $sessionId, $product, $quantity): CartItem {
            $cart = $this->current($user, $sessionId);
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->getKey());
            $currentProduct = Product::query()->lockForUpdate()->find($product->getKey());
            $this->assertProductAvailable($currentProduct);

            $item = CartItem::query()
                ->where('cart_id', $cart->getKey())
                ->where('product_id', $currentProduct->getKey())
                ->lockForUpdate()
                ->first();

            $newQuantity = ($item?->quantity ?? 0) + $quantity;
            $this->assertStock($currentProduct, $newQuantity);

            if ($item) {
                $item->quantity = $newQuantity;
                $item->save();
            } else {
                $item = $cart->items()->create([
                    'product_id' => $currentProduct->getKey(),
                    'quantity' => $newQuantity,
                ]);
            }

            $this->assertSubtotalRepresentable($cart);

            return $item->load(['product.images']);
        });
    }

    public function update(?User $user, string $sessionId, CartItem $item, int $quantity): CartItem
    {
        $this->validateQuantity($quantity);

        return DB::transaction(function () use ($user, $sessionId, $item, $quantity): CartItem {
            $currentCart = $this->findCurrent($user, $sessionId);
            abort_unless($currentCart && (int) $item->cart_id === (int) $currentCart->getKey(), 404);
            $cart = Cart::query()->lockForUpdate()->findOrFail($currentCart->getKey());

            $lockedItem = CartItem::query()->lockForUpdate()->findOrFail($item->getKey());
            abort_unless((int) $lockedItem->cart_id === (int) $cart->getKey(), 404);
            $product = Product::query()->lockForUpdate()->find($lockedItem->product_id);
            $this->assertProductAvailable($product);
            $this->assertStock($product, $quantity);

            $lockedItem->quantity = $quantity;
            $lockedItem->save();
            $this->assertSubtotalRepresentable($cart);

            return $lockedItem->load(['product.images']);
        });
    }

    public function remove(?User $user, string $sessionId, CartItem $item): void
    {
        DB::transaction(function () use ($user, $sessionId, $item): void {
            $currentCart = $this->findCurrent($user, $sessionId);
            abort_unless($currentCart && (int) $item->cart_id === (int) $currentCart->getKey(), 404);
            $cart = Cart::query()->lockForUpdate()->findOrFail($currentCart->getKey());

            CartItem::query()
                ->whereKey($item->getKey())
                ->where('cart_id', $cart->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });
    }

    public function clear(?User $user, string $sessionId): void
    {
        DB::transaction(function () use ($user, $sessionId): void {
            $cart = $this->lockCurrent($user, $sessionId);

            if ($cart) {
                $this->clearItems($cart);
            }
        });
    }

    /** Clear items on a cart already locked by a surrounding transaction. */
    public function clearItems(Cart $cart): void
    {
        CartItem::query()->where('cart_id', $cart->getKey())->delete();
    }

    public function subtotalMinor(Cart $cart): int
    {
        $cart->loadMissing('items.product');

        return $cart->items->reduce(function (int $subtotal, CartItem $item): int {
            if (! $item->product) {
                return $subtotal;
            }

            $lineTotal = Money::multiplyMinor(Money::toMinor((string) $item->product->price), $item->quantity);

            return Money::addMinor($subtotal, $lineTotal);
        }, 0);
    }

    public function subtotal(Cart $cart): string
    {
        return Money::fromMinor($this->subtotalMinor($cart));
    }

    /**
     * Merge an old, pre-login guest session cart into the customer's persistent cart.
     * Quantities are clamped to current stock; inactive/out-of-stock guest lines are skipped.
     *
     * @return array{skipped: int, adjusted: int}
     */
    public function mergeGuestCart(User $user, string $guestSessionId): array
    {
        if ($guestSessionId === '') {
            return ['skipped' => 0, 'adjusted' => 0];
        }

        $guestCart = Cart::query()->where('session_id', $this->guestSessionKey($guestSessionId))->first();
        if (! $guestCart) {
            return ['skipped' => 0, 'adjusted' => 0];
        }

        return DB::transaction(function () use ($user, $guestCart): array {
            $guestCart = Cart::query()->lockForUpdate()->find($guestCart->getKey());
            if (! $guestCart || $guestCart->user_id !== null) {
                return ['skipped' => 0, 'adjusted' => 0];
            }

            $userCart = $user->cart()->firstOrCreate([], ['session_id' => null]);
            $userCart = Cart::query()->lockForUpdate()->findOrFail($userCart->getKey());
            $skipped = 0;
            $adjusted = 0;

            $guestItems = CartItem::query()
                ->where('cart_id', $guestCart->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($guestItems as $guestItem) {
                $product = Product::query()->lockForUpdate()->find($guestItem->product_id);
                if (! $product || ! $product->is_active || $product->stock_quantity < 1) {
                    $guestItem->delete();
                    $skipped++;
                    continue;
                }

                $userItem = CartItem::query()
                    ->where('cart_id', $userCart->getKey())
                    ->where('product_id', $product->getKey())
                    ->lockForUpdate()
                    ->first();

                $requestedQuantity = ($userItem?->quantity ?? 0) + $guestItem->quantity;
                $targetQuantity = min($requestedQuantity, (int) $product->stock_quantity);

                if ($requestedQuantity > $targetQuantity) {
                    $adjusted++;
                }

                if ($targetQuantity > 0) {
                    if ($userItem) {
                        $userItem->quantity = $targetQuantity;
                        $userItem->save();
                    } else {
                        $userCart->items()->create([
                            'product_id' => $product->getKey(),
                            'quantity' => $targetQuantity,
                        ]);
                    }
                }

                $guestItem->delete();
            }

            $this->assertSubtotalRepresentable($userCart);
            $guestCart->delete();

            return ['skipped' => $skipped, 'adjusted' => $adjusted];
        });
    }

    private function assertSubtotalRepresentable(Cart $cart): void
    {
        try {
            $cart->unsetRelation('items');
            $this->subtotalMinor($cart);
        } catch (OverflowException) {
            throw ValidationException::withMessages([
                'quantity' => 'The cart total exceeds the supported amount range. Please contact customer support.',
            ]);
        }
    }

    private function guestSessionKey(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Choose a quantity of at least one.']);
        }
    }

    private function assertProductAvailable(?Product $product): void
    {
        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages(['product' => 'This product is no longer available.']);
        }

        if ($product->stock_quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'This product is currently out of stock.']);
        }
    }

    private function assertStock(Product $product, int $quantity): void
    {
        if ($quantity > $product->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Only '.$product->stock_quantity.' unit(s) are currently available.',
            ]);
        }
    }

}
