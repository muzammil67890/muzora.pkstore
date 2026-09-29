<?php

namespace App\Policies;

use App\Models\Admin;

class StoreSettingPolicy
{
    public function manage(Admin $admin): bool
    {
        return $admin->is_active;
    }
}
