<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly OrderService $orders
    ) {
    }

    public function create(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');
        $couponCode = $request->session()->get('checkout.coupon_code');
        $quote = $this->orders->preview($user, is_string($couponCode) ? $couponCode : null);

        if ($quote['items'] === []) {
            return redirect()->route('cart')->with('status', 'Your cart is empty. Add products before checkout.');
        }

        $token = $request->session()->get('checkout.idempotency_token');
        if (! is_string($token) || ! Str::isUuid($token)) {
            $token = (string) Str::uuid();
            $request->session()->put('checkout.idempotency_token', $token);
        }

        return view('storefront', [
            'page' => 'checkout',
            'cartCount' => $this->carts->countCurrent($user, $request->session()->getId()),
            'checkoutQuote' => $quote,
            'checkoutToken' => $token,
            'checkoutUser' => $user,
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->whereIn('slug', array_keys(PaymentMethod::AVAILABLE_METHODS))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');
        $token = $request->validated('checkout_token');
        $sessionToken = $request->session()->get('checkout.idempotency_token');

        if (! is_string($sessionToken) || ! hash_equals($sessionToken, $token)) {
            $existing = $this->orders->findByIdempotencyKey($user, $token);
            abort_unless($existing, 419);
            $request->session()->forget('checkout.coupon_code');

            return redirect()->route('orders.success', $existing)->with('status', 'This checkout was already submitted.');
        }

        $order = $this->orders->createFromCart(
            $user,
            $request->customerData(),
            $token,
            (int) $request->validated('payment_method_id'),
            is_string($request->session()->get('checkout.coupon_code'))
                ? $request->session()->get('checkout.coupon_code')
                : null
        );
        $request->session()->forget(['checkout.idempotency_token', 'checkout.coupon_code']);

        return redirect()->route('orders.success', $order)->with('status', 'Your order has been placed.');
    }

    public function success(Request $request, Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['items', 'paymentMethod', 'paymentProofs.reviewer']);

        return view('orders.success', ['order' => $order]);
    }
}
