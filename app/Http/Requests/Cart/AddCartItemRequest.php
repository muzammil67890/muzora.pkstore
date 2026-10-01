<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product' => ['required', 'string', 'exists:products,slug'],
            'quantity' => ['required', 'integer', 'min:1'],
            'buy_now' => ['sometimes', 'boolean'],
        ];
    }
}
