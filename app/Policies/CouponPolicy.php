<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Coupon;

class CouponPolicy
{
    public function adminViewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function adminCreate(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function adminUpdate(Admin $admin, Coupon $coupon): bool
    {
        return $admin->is_active;
    }
}
