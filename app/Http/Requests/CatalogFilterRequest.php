<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:140'],
            'brand' => ['nullable', 'string', 'max:140'],
            'sort' => ['nullable', 'in:featured,newest,price-asc,price-desc'],
            'in_stock' => ['sometimes', 'boolean'],
            'on_sale' => ['sometimes', 'boolean'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
