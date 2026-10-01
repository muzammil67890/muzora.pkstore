<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->is_active;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $data = [
            'code' => is_string($code) ? strtoupper(trim($code)) : $code,
        ];
        if (! array_key_exists('is_active', $this->all())) {
            $data['is_active'] = false;
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');
        $codeUnique = Rule::unique('coupons', 'code');
        if ($coupon instanceof Coupon) {
            $codeUnique->ignore($coupon->getKey());
        }

        $valueRules = $this->input('type') === 'percentage'
            ? ['required', 'integer', 'between:1,100']
            : ['required', 'regex:/^\\d{1,10}(?:\\.\\d{1,2})?$/'];

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/', $codeUnique],
            'type' => ['required', Rule::in(Coupon::TYPES)],
            'value' => $valueRules,
            'minimum_order_amount' => ['nullable', 'regex:/^\\d{1,10}(?:\\.\\d{1,2})?$/'],
            'maximum_discount_amount' => ['nullable', 'regex:/^\\d{1,10}(?:\\.\\d{1,2})?$/'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('starts_at');
            $end = $this->input('expires_at');
            if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                $validator->errors()->add('expires_at', 'The coupon expiry must be on or after its start time.');
            }

            if ($this->input('type') === 'fixed' && preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/', (string) $this->input('value')) === 1) {
                $parts = explode('.', (string) $this->input('value'), 2);
                if ((int) $parts[0] === 0 && (int) str_pad($parts[1] ?? '0', 2, '0') === 0) {
                    $validator->errors()->add('value', 'The fixed discount must be greater than zero.');
                }
            }

            $maximum = $this->input('maximum_discount_amount');
            if ($maximum !== null && $maximum !== ''
                && preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/', (string) $maximum) === 1) {
                $parts = explode('.', (string) $maximum, 2);
                if ((int) $parts[0] === 0 && (int) str_pad($parts[1] ?? '0', 2, '0') === 0) {
                    $validator->errors()->add('maximum_discount_amount', 'The maximum discount must be greater than zero when provided.');
                }
            }
        });
    }
}
