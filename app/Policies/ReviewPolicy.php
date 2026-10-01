<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function create(User $user, Product $product): bool
    {
        return $product->is_active;
    }

    public function moderateAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function moderate(Admin $admin, Review $review): bool
    {
        return $admin->is_active;
    }
}
