<?php

namespace App\Services\Wishlist;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\Cart\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WishlistService
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function current(User $user): Wishlist
    {
        return $user->wishlist()->firstOrCreate([]);
    }

    public function items(User $user)
    {
        return $this->current($user)
            ->items()
            ->with([
                'product.category',
                'product.brand',
                'product.images' => fn ($query) => $query->reorder()->orderByDesc('is_primary')->orderBy('position'),
            ])
            ->latest('wishlist_items.created_at')
            ->get();
    }

    public function count(User $user): int
    {
        return $user->wishlist?->items()->count() ?? 0;
    }

    public function add(User $user, Product $product): WishlistItem
    {
        return DB::transaction(function () use ($user, $product): WishlistItem {
            $currentProduct = Product::query()->lockForUpdate()->find($product->getKey());
            $this->assertProductAvailable($currentProduct);
            $wishlist = $this->current($user);

            $item = $wishlist->items()->firstOrCreate(['product_id' => $currentProduct->getKey()]);

            return $item->load(['product.category', 'product.brand', 'product.images']);
        });
    }

    /** @return array{added: bool} */
    public function toggle(User $user, Product $product): array
    {
        return DB::transaction(function () use ($user, $product): array {
            $currentProduct = Product::query()->lockForUpdate()->find($product->getKey());
            $this->assertProductAvailable($currentProduct);
            $wishlist = $this->current($user);
            $item = $wishlist->items()->where('product_id', $currentProduct->getKey())->lockForUpdate()->first();

            if ($item) {
                $item->delete();
                return ['added' => false];
            }

            $wishlist->items()->create(['product_id' => $currentProduct->getKey()]);

            return ['added' => true];
        });
    }

    public function remove(User $user, Product $product): void
    {
        DB::transaction(function () use ($user, $product): void {
            $wishlist = $user->wishlist()->first();
            if (! $wishlist) {
                return;
            }

            $wishlist->items()
                ->where('product_id', $product->getKey())
                ->lockForUpdate()
                ->delete();
        });
    }

    public function moveToCart(User $user, Product $product, string $sessionId): CartItem
    {
        return DB::transaction(function () use ($user, $product, $sessionId): CartItem {
            $wishlist = $user->wishlist()->first();
            abort_unless($wishlist, 404);

            $item = $wishlist->items()
                ->where('product_id', $product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $cartItem = $this->cartService->add($user, $sessionId, $product, 1);
            $item->delete();

            return $cartItem;
        });
    }

    private function assertProductAvailable(?Product $product): void
    {
        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages(['product' => 'This product is no longer available.']);
        }
    }
}
