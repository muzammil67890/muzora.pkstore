<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null && $this->route('product') instanceof Product;
    }

    public function rules(): array
    {
        $userId = $this->user('web')?->getKey();

        return [
            'order_id' => [
                'required', 'integer',
                Rule::exists('orders', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:8', 'max:4000'],
        ];
    }
}
