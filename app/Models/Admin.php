<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public function reviewedPaymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class, 'reviewed_by');
    }

    public function moderatedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'moderated_by');
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
