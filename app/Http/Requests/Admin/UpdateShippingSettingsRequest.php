<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->is_active;
    }

    public function rules(): array
    {
        $money = ['required', 'regex:/^\\d{1,10}(?:\\.\\d{1,2})?$/'];

        return [
            'shipping_standard_fee' => $money,
            'shipping_free_threshold' => $money,
            'minimum_order_amount' => $money,
        ];
    }
}
