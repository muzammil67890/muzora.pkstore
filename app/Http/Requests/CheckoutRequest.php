<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_email' => strtolower(trim((string) $this->input('customer_email'))),
            'customer_phone' => trim((string) $this->input('customer_phone')),
            'shipping_address' => trim((string) $this->input('shipping_address')),
            'city' => trim((string) $this->input('city')),
            'province' => trim((string) $this->input('province')),
            'postal_code' => trim((string) $this->input('postal_code')),
            'customer_notes' => trim((string) $this->input('customer_notes')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user('web');
        $token = $this->input('checkout_token');

        if (! $user || ! is_string($token) || ! Str::isUuid($token)) {
            return false;
        }

        $sessionToken = $this->session()->get('checkout.idempotency_token');
        if (is_string($sessionToken) && hash_equals($sessionToken, $token)) {
            return true;
        }

        return Order::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $token)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'uuid'],
            'payment_method_id' => [
                'required', 'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('slug', array_keys(PaymentMethod::AVAILABLE_METHODS))),
            ],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'string', 'email:rfc', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:30'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public function customerData(): array
    {
        $data = $this->safe()->only([
            'customer_name', 'customer_email', 'customer_phone', 'shipping_address',
            'city', 'province', 'postal_code', 'customer_notes',
        ]);

        return [
            'name' => $data['customer_name'],
            'email' => $data['customer_email'],
            'phone' => $data['customer_phone'],
            'address' => $data['shipping_address'],
            'city' => $data['city'],
            'province' => $data['province'],
            'postal_code' => $data['postal_code'],
            'notes' => $data['customer_notes'] ?? null,
        ];
    }
}
