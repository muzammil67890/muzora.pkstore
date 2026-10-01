<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'viewAny', PaymentMethod::class);

        return view('admin.payment-methods.index', [
            'paymentMethods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'create', PaymentMethod::class);
        $availableMethods = array_filter(
            PaymentMethod::AVAILABLE_METHODS,
            fn (array $method, string $slug): bool => ! PaymentMethod::query()->where('slug', $slug)->exists(),
            ARRAY_FILTER_USE_BOTH
        );

        return view('admin.payment-methods.form', ['creating' => true, 'availableMethods' => $availableMethods]);
    }

    public function store(SavePaymentMethodRequest $request): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'create', PaymentMethod::class);
        $data = $request->safe()->only([
            'method', 'account_title', 'account_number', 'iban', 'mobile_number',
            'instructions', 'is_active', 'sort_order',
        ]);
        $identity = PaymentMethod::AVAILABLE_METHODS[$data['method']];

        PaymentMethod::query()->create([
            'name' => $identity['name'],
            'slug' => $data['method'],
            'type' => $identity['type'],
            'account_title' => $data['account_title'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'iban' => $data['iban'] ?? null,
            'mobile_number' => $data['mobile_number'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        return redirect()->route('admin.payment-methods.index')->with('status', 'Payment method created.');
    }

    public function edit(Request $request, PaymentMethod $paymentMethod): View
    {
        $this->authorizeForUser($request->user('admin'), 'update', $paymentMethod);

        return view('admin.payment-methods.form', ['creating' => false, 'paymentMethod' => $paymentMethod]);
    }

    public function update(SavePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'update', $paymentMethod);
        $paymentMethod->fill($request->safe()->only([
            'account_title', 'account_number', 'iban', 'mobile_number',
            'instructions', 'is_active', 'sort_order',
        ]));
        $paymentMethod->save();

        return redirect()->route('admin.payment-methods.index')->with('status', 'Payment method updated.');
    }
}
