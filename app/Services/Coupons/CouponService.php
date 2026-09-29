<?php

namespace App\Services\Coupons;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @return array{coupon: ?Coupon, code: ?string, discount_minor: int, error: ?string}
     */
    public function quote(?string $code, User $user, int $subtotalMinor, bool $lock = false): array
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return ['coupon' => null, 'code' => null, 'discount_minor' => 0, 'error' => null];
        }

        $query = Coupon::query()->where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();
        if (! $coupon || ! $coupon->is_active) {
            return $this->invalid('That coupon code is not available.');
        }

        $now = now();
        if (($coupon->starts_at && $coupon->starts_at->isAfter($now))
            || ($coupon->expires_at && $coupon->expires_at->lessThanOrEqualTo($now))) {
            return $this->invalid('That coupon is not currently within its validity period.');
        }

        if (($coupon->minimum_order_amount_minor !== null && $coupon->minimum_order_amount_minor < 0)
            || ($coupon->maximum_discount_amount_minor !== null && $coupon->maximum_discount_amount_minor < 0)
            || $coupon->used_count < 0) {
            return $this->invalid('This coupon has invalid stored limits.');
        }

        if ($coupon->minimum_order_amount_minor !== null && $subtotalMinor < $coupon->minimum_order_amount_minor) {
            return $this->invalid('Your cart does not meet the minimum order amount for this coupon.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return $this->invalid('That coupon has reached its total usage limit.');
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $usageQuery = CouponUsage::query()
                ->where('coupon_id', $coupon->getKey())
                ->where('user_id', $user->getKey());
            // Use a current locking read after the coupon row lock to serialize final per-customer limit checks.
            if ($lock) {
                $usageQuery->lockForUpdate();
            }
            if ($usageQuery->pluck('id')->count() >= $coupon->usage_limit_per_customer) {
                return $this->invalid('You have reached the usage limit for this coupon.');
            }
        }

        if ($coupon->type === 'percentage' && ($coupon->value < 1 || $coupon->value > 100)) {
            return $this->invalid('This coupon has an invalid percentage value.');
        }
        if ($coupon->type === 'fixed' && $coupon->value < 1) {
            return $this->invalid('This coupon has an invalid fixed discount value.');
        }
        if (! in_array($coupon->type, Coupon::TYPES, true)) {
            return $this->invalid('This coupon has an unsupported discount type.');
        }

        $discount = $coupon->type === 'percentage'
            ? $this->percentageDiscount($subtotalMinor, $coupon->value)
            : min($subtotalMinor, $coupon->value);

        if ($coupon->maximum_discount_amount_minor !== null) {
            $discount = min($discount, $coupon->maximum_discount_amount_minor);
        }
        $discount = min($subtotalMinor, max(0, $discount));
        if ($discount < 1) {
            return $this->invalid('This coupon does not produce a discount for the current cart.');
        }

        return ['coupon' => $coupon, 'code' => $coupon->code, 'discount_minor' => $discount, 'error' => null];
    }

    public function recordUsage(Coupon $coupon, User $user, Order $order, int $discountMinor): void
    {
        $update = Coupon::query()->whereKey($coupon->getKey());
        if ($coupon->usage_limit !== null) {
            $update->where('used_count', '<', $coupon->usage_limit);
        }
        if ($update->increment('used_count') !== 1) {
            throw ValidationException::withMessages(['coupon_code' => 'That coupon has reached its total usage limit.']);
        }

        $coupon->used_count++;
        CouponUsage::query()->create([
            'coupon_id' => $coupon->getKey(),
            'user_id' => $user->getKey(),
            'order_id' => $order->getKey(),
            'coupon_code' => $coupon->code,
            'discount_amount_minor' => $discountMinor,
        ]);
    }

    public function assertValidForOrder(?string $code, User $user, int $subtotalMinor): array
    {
        $quote = $this->quote($code, $user, $subtotalMinor, lock: true);
        if ($quote['error'] !== null) {
            throw ValidationException::withMessages(['coupon_code' => $quote['error']]);
        }

        return $quote;
    }

    private function percentageDiscount(int $subtotalMinor, int $percentage): int
    {
        // Split before multiplication to keep the integer intermediate within PHP_INT_MAX.
        return intdiv($subtotalMinor, 100) * $percentage
            + intdiv(($subtotalMinor % 100) * $percentage, 100);
    }

    /** @return array{coupon: null, code: null, discount_minor: 0, error: string} */
    private function invalid(string $message): array
    {
        return ['coupon' => null, 'code' => null, 'discount_minor' => 0, 'error' => $message];
    }
}
