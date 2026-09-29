<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Admin && $this->user('admin')->is_active;
    }

    public function rules(): array
    {
        return [
            'order_status' => ['required', Rule::in(Order::ORDER_STATUSES)],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ];
    }
}
