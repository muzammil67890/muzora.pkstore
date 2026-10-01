<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subtotalMinor(): int
    {
        if (! $this->product) {
            return 0;
        }

        return Money::multiplyMinor(Money::toMinor((string) $this->product->price), $this->quantity);
    }

    public function formattedUnitPrice(): string
    {
        return $this->product ? Money::formatMinor(Money::toMinor((string) $this->product->price)) : '0.00';
    }

    public function formattedSubtotal(): string
    {
        return Money::formatMinor($this->subtotalMinor());
    }
}
