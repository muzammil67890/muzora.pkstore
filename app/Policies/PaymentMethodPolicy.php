<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PaymentMethod;

class PaymentMethodPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, PaymentMethod $paymentMethod): bool
    {
        return $admin->is_active;
    }
}
