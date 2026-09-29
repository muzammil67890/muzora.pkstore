<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Coupon extends Model
{
    public const TYPES = ['percentage', 'fixed'];

    protected $fillable = [
        'code', 'type', 'value', 'minimum_order_amount_minor', 'maximum_discount_amount_minor',
        'usage_limit', 'usage_limit_per_customer', 'used_count', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'minimum_order_amount_minor' => 'integer',
            'maximum_discount_amount_minor' => 'integer',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper(trim($value));
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function formattedValue(string $currency = 'PKR'): string
    {
        return $this->type === 'percentage'
            ? $this->value.'%'
            : $currency.' '.Money::formatMinor($this->value);
    }
}
