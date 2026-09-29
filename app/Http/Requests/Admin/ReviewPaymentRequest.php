<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReviewPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->is_active;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:verified,rejected'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('decision') === 'rejected' && trim((string) $this->input('rejection_reason')) === '') {
                $validator->errors()->add('rejection_reason', 'A reason is required when rejecting a payment.');
            }
        });
    }
}
