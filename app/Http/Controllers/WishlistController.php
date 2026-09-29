<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Wishlist\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlists, private readonly CartService $carts)
    {
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user('web');

        return view('account.wishlist', [
            'wishlistItems' => $this->wishlists->items($user),
            'wishlistCount' => $this->wishlists->count($user),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $item = $this->wishlists->add($request->user('web'), $product);
        $message = $item->wasRecentlyCreated ? 'Product added to your wishlist.' : 'Product is already in your wishlist.';

        return back()->with('status', $message);
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $result = $this->wishlists->toggle($request->user('web'), $product);

        return back()->with('status', $result['added'] ? 'Product added to your wishlist.' : 'Product removed from your wishlist.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->wishlists->remove($request->user('web'), $product);

        return back()->with('status', 'Product removed from your wishlist.');
    }

    public function moveToCart(Request $request, Product $product): RedirectResponse
    {
        $this->wishlists->moveToCart($request->user('web'), $product, $request->session()->getId());
        $cartCount = $this->carts->countCurrent($request->user('web'), $request->session()->getId());

        return back()->with('status', 'Product moved to your cart ('.$cartCount.' item(s) total).');
    }
}
