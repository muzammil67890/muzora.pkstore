<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    public const STATUSES = ['submitted', 'verified', 'rejected'];

    protected $fillable = [
        'order_id', 'user_id', 'reviewed_by', 'transaction_id', 'amount_minor',
        'screenshot_path', 'notes', 'status', 'submitted_at', 'reviewed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
