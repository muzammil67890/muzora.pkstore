<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->is_active;
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:120'],
            'store_email' => ['nullable', 'email:rfc', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{5,30}$/'],
            'store_address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', Rule::in(['PKR', 'USD', 'EUR', 'GBP', 'AED', 'SAR'])],
            'footer_text' => ['nullable', 'string', 'max:500'],
        ];
    }
}
