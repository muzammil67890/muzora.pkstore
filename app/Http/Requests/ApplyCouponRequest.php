<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('coupon_code');
        $this->merge(['coupon_code' => is_string($code) ? strtoupper(trim($code)) : $code]);
    }

    public function rules(): array
    {
        return ['coupon_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/']];
    }
}
