<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');
        $order = $this->route('order');

        return $user !== null
            && $order instanceof Order
            && (int) $order->user_id === (int) $user->getKey();
    }

    protected function prepareForValidation(): void
    {
        $transactionId = $this->input('transaction_id');
        $this->merge([
            'transaction_id' => is_string($transactionId) ? trim($transactionId) : $transactionId,
            'notes' => is_string($this->input('notes')) ? trim($this->input('notes')) : $this->input('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9\\/._ -]*$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'screenshot' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp', 'max:5120',
            ],
        ];
    }
}
