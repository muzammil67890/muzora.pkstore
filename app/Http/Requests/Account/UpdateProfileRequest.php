<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => $this->input('phone') === null ? null : trim((string) $this->input('phone')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user('web')->getKey())],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{7,30}$/'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
