<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

    public const PAYMENT_STATUSES = ['pending', 'submitted', 'verified', 'rejected'];

    protected $fillable = [
        'user_id', 'order_number', 'idempotency_key', 'customer_name', 'customer_email',
        'customer_phone', 'shipping_address', 'city', 'province', 'postal_code', 'customer_notes',
        'subtotal_minor', 'shipping_amount_minor', 'discount_amount_minor', 'total_amount_minor',
        'currency', 'payment_method_id', 'coupon_id', 'coupon_code', 'payment_status', 'order_status', 'tracking_number',
        'confirmed_at', 'processing_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'stock_restored_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'shipping_amount_minor' => 'integer',
            'discount_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'payment_method_id' => 'integer',
            'coupon_id' => 'integer',
            'confirmed_at' => 'datetime',
            'processing_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'stock_restored_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->latest('submitted_at');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return list<string> */
    public function allowedNextStatuses(): array
    {
        return match ($this->order_status) {
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            default => [],
        };
    }
}
