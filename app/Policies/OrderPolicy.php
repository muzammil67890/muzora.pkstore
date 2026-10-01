<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return (int) $order->user_id === (int) $user->getKey();
    }

    public function adminViewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function adminView(Admin $admin, Order $order): bool
    {
        return $admin->is_active;
    }

    public function adminUpdate(Admin $admin, Order $order): bool
    {
        return $admin->is_active;
    }
}
