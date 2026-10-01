<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyCouponRequest;
use App\Models\User;
use App\Services\Checkout\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutCouponController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function apply(ApplyCouponRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');
        $code = $request->validated('coupon_code');
        $quote = $this->orders->preview($user, $code);

        if ($quote['coupon_error'] !== null) {
            throw ValidationException::withMessages(['coupon_code' => $quote['coupon_error']]);
        }

        $request->session()->put('checkout.coupon_code', $quote['coupon_code']);

        return redirect()->route('checkout.create')->with('status', 'Coupon applied. The discount will be revalidated when you place the order.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $request->session()->forget('checkout.coupon_code');

        return redirect()->route('checkout.create')->with('status', 'Coupon removed.');
    }
}
