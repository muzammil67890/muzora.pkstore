<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PaymentProof;
use App\Models\User;

class PaymentProofPolicy
{
    public function view(User $user, PaymentProof $paymentProof): bool
    {
        return (int) $paymentProof->user_id === (int) $user->getKey()
            && (int) ($paymentProof->order?->user_id) === (int) $user->getKey();
    }

    public function adminView(Admin $admin, PaymentProof $paymentProof): bool
    {
        return $admin->is_active;
    }
}
