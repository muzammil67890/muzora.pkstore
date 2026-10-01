<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCouponRequest;
use App\Models\Coupon;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'adminViewAny', Coupon::class);

        return view('admin.coupons.index', [
            'coupons' => Coupon::query()->withCount('usages')->latest()->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'adminCreate', Coupon::class);

        return view('admin.coupons.form', ['coupon' => new Coupon(), 'creating' => true]);
    }

    public function store(SaveCouponRequest $request): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'adminCreate', Coupon::class);
        $data = $this->databaseValues($request->safe()->only([
            'code', 'type', 'value', 'minimum_order_amount', 'maximum_discount_amount',
            'usage_limit', 'usage_limit_per_customer', 'starts_at', 'expires_at', 'is_active',
        ]));
        Coupon::query()->create($data);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created.');
    }

    public function edit(Request $request, Coupon $coupon): View
    {
        $this->authorizeForUser($request->user('admin'), 'adminUpdate', $coupon);

        return view('admin.coupons.form', ['coupon' => $coupon, 'creating' => false]);
    }

    public function update(SaveCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'adminUpdate', $coupon);
        $coupon->fill($this->databaseValues($request->safe()->only([
            'code', 'type', 'value', 'minimum_order_amount', 'maximum_discount_amount',
            'usage_limit', 'usage_limit_per_customer', 'starts_at', 'expires_at', 'is_active',
        ])));
        $coupon->save();

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon updated.');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function databaseValues(array $data): array
    {
        $data['code'] = strtoupper(trim($data['code']));
        $data['value'] = $data['type'] === 'percentage'
            ? (int) $data['value']
            : Money::toMinor((string) $data['value']);
        $data['minimum_order_amount_minor'] = isset($data['minimum_order_amount']) && $data['minimum_order_amount'] !== ''
            ? Money::toMinor((string) $data['minimum_order_amount']) : null;
        $data['maximum_discount_amount_minor'] = isset($data['maximum_discount_amount']) && $data['maximum_discount_amount'] !== ''
            ? Money::toMinor((string) $data['maximum_discount_amount']) : null;
        unset($data['minimum_order_amount'], $data['maximum_discount_amount']);

        return $data;
    }
}
