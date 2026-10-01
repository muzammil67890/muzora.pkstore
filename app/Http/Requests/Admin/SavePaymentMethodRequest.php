<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->is_active;
    }

    protected function prepareForValidation(): void
    {
        $iban = $this->input('iban');
        $normalized = [
            'iban' => is_string($iban) ? strtoupper(str_replace(' ', '', trim($iban))) : $iban,
            'mobile_number' => is_string($this->input('mobile_number')) ? trim($this->input('mobile_number')) : $this->input('mobile_number'),
        ];
        if (! array_key_exists('is_active', $this->all())) {
            $normalized['is_active'] = false;
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        $creating = $this->routeIs('admin.payment-methods.store');
        $methodRules = $creating
            ? ['required', Rule::in(array_keys(PaymentMethod::AVAILABLE_METHODS)), Rule::unique('payment_methods', 'slug')]
            : ['prohibited'];

        return [
            'method' => $methodRules,
            'account_title' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9 ._-]+$/'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/'],
            'mobile_number' => ['nullable', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{7,30}$/'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_active')) {
                return;
            }

            $slug = $this->route('paymentMethod') instanceof PaymentMethod
                ? $this->route('paymentMethod')->slug
                : $this->input('method');
            $method = PaymentMethod::AVAILABLE_METHODS[$slug] ?? null;
            if (! $method) {
                return;
            }

            if ($method['type'] === 'bank') {
                if (trim((string) $this->input('account_title')) === '') {
                    $validator->errors()->add('account_title', 'An account title is required to activate a bank method.');
                }
                if (trim((string) $this->input('account_number')) === '' && trim((string) $this->input('iban')) === '') {
                    $validator->errors()->add('account_number', 'Provide an account number or IBAN before activating this bank method.');
                }
            } elseif (trim((string) $this->input('mobile_number')) === '') {
                $validator->errors()->add('mobile_number', 'A mobile number is required to activate this wallet method.');
            }
        });
    }
}
