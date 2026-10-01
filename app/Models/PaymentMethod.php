<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    /** Only these manual/offline methods are supported by the application. */
    public const AVAILABLE_METHODS = [
        'bank-alfalah' => ['name' => 'Bank Alfalah', 'type' => 'bank'],
        'meezan-bank' => ['name' => 'Meezan Bank', 'type' => 'bank'],
        'easypaisa' => ['name' => 'EasyPaisa', 'type' => 'mobile_wallet'],
        'jazzcash' => ['name' => 'JazzCash', 'type' => 'mobile_wallet'],
    ];

    protected $fillable = [
        'name', 'slug', 'type', 'account_title', 'account_number', 'iban',
        'mobile_number', 'instructions', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
