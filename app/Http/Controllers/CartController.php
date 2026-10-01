<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts)
    {
    }

    public function index(Request $request): View
    {
        $cart = $this->carts->current($request->user('web'), $request->session()->getId());
        $cart->load([
            'items.product.category',
            'items.product.brand',
            'items.product.images' => fn ($query) => $query->reorder()->orderByDesc('is_primary')->orderBy('position'),
        ]);
        $subtotalMinor = $this->carts->subtotalMinor($cart);

        return view('storefront', [
            'page' => 'cart',
            'cart' => $cart,
            'cartCount' => $this->carts->countCurrent($request->user('web'), $request->session()->getId()),
            'cartSubtotal' => Money::fromMinor($subtotalMinor),
            'cartSubtotalFormatted' => Money::formatMinor($subtotalMinor),
        ]);
    }

    public function store(AddCartItemRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->safe()->only(['product', 'quantity', 'buy_now']);
        $product = Product::query()->where('slug', $data['product'])->firstOrFail();
        $item = $this->carts->add($request->user('web'), $request->session()->getId(), $product, (int) $data['quantity']);
        $cart = $this->carts->current($request->user('web'), $request->session()->getId());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $product->name.' added to your cart.',
                'cart_count' => $this->carts->countCurrent($request->user('web'), $request->session()->getId()),
                'subtotal' => $this->carts->subtotal($cart),
                'item_id' => $item->getKey(),
            ], 201);
        }

        $redirect = ! empty($data['buy_now']) ? redirect()->route('cart') : back();

        return $redirect->with('status', $product->name.' added to your cart.');
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse|RedirectResponse
    {
        $item = $this->carts->update(
            $request->user('web'),
            $request->session()->getId(),
            $cartItem,
            (int) $request->validated('quantity')
        );
        $cart = $this->carts->current($request->user('web'), $request->session()->getId());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cart quantity updated.',
                'cart_count' => $this->carts->countCurrent($request->user('web'), $request->session()->getId()),
                'subtotal' => $this->carts->subtotal($cart),
                'item_subtotal' => Money::fromMinor($item->subtotalMinor()),
            ]);
        }

        return back()->with('status', 'Cart quantity updated.');
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse|RedirectResponse
    {
        $this->carts->remove($request->user('web'), $request->session()->getId(), $cartItem);
        $cart = $this->carts->current($request->user('web'), $request->session()->getId());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product removed from your cart.',
                'cart_count' => $this->carts->countCurrent($request->user('web'), $request->session()->getId()),
                'subtotal' => $this->carts->subtotal($cart),
            ]);
        }

        return back()->with('status', 'Product removed from your cart.');
    }

    public function clear(Request $request): JsonResponse|RedirectResponse
    {
        $this->carts->clear($request->user('web'), $request->session()->getId());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your cart has been cleared.',
                'cart_count' => 0,
                'subtotal' => '0.00',
            ]);
        }

        return back()->with('status', 'Your cart has been cleared.');
    }

}
