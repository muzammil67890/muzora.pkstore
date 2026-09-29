<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Admin && $this->user('admin')->is_active;
    }

    public function rules(): array
    {
        $toRules = ['nullable', 'date'];
        if ($this->filled('from')) {
            $toRules[] = 'after_or_equal:from';
        }

        return [
            'order_number' => ['nullable', 'string', 'max:64'],
            'customer' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])],
            'from' => ['nullable', 'date'],
            'to' => $toRules,
        ];
    }
}
