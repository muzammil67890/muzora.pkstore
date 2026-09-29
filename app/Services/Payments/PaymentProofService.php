<?php

namespace App\Services\Payments;

use App\Models\Admin;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentProofService
{
    /** @param array{transaction_id?: ?string, notes?: ?string} $data */
    public function submit(Order $order, User $user, array $data, UploadedFile $screenshot): PaymentProof
    {
        $path = null;

        try {
            return DB::transaction(function () use ($order, $user, $data, $screenshot, &$path): PaymentProof {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

                if ((int) $lockedOrder->user_id !== (int) $user->getKey()) {
                    abort(404);
                }

                if (! $lockedOrder->payment_method_id || ! $lockedOrder->paymentMethod()->exists()) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'This order has no available manual payment method. Contact customer support for assistance.',
                    ]);
                }

                if ($lockedOrder->order_status === 'cancelled'
                    || ! in_array($lockedOrder->payment_status, ['pending', 'rejected'], true)) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'A payment proof cannot be submitted for this order in its current payment state.',
                    ]);
                }

                $path = $screenshot->store('orders/'.$lockedOrder->getKey(), 'payment-proofs');
                if (! is_string($path) || $path === '') {
                    throw new \RuntimeException('The payment proof could not be stored.');
                }

                $proof = $lockedOrder->paymentProofs()->create([
                    'user_id' => $user->getKey(),
                    'transaction_id' => ($data['transaction_id'] ?? null) ?: null,
                    // This is a server-side snapshot, never a customer-entered payment amount.
                    'amount_minor' => $lockedOrder->total_amount_minor,
                    'screenshot_path' => $path,
                    'notes' => ($data['notes'] ?? null) ?: null,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $lockedOrder->payment_status = 'submitted';
                $lockedOrder->save();

                return $proof;
            });
        } catch (Throwable $exception) {
            if (is_string($path) && $path !== '') {
                Storage::disk('payment-proofs')->delete($path);
            }

            throw $exception;
        }
    }

    public function review(Order $order, Admin $admin, string $decision, ?string $rejectionReason = null): PaymentProof
    {
        return DB::transaction(function () use ($order, $admin, $decision, $rejectionReason): PaymentProof {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($lockedOrder->payment_status !== 'submitted') {
                throw ValidationException::withMessages([
                    'payment_status' => 'This order does not have a payment awaiting review.',
                ]);
            }

            $proof = $lockedOrder->paymentProofs()
                ->where('status', 'submitted')
                ->lockForUpdate()
                ->first();

            if (! $proof) {
                throw ValidationException::withMessages([
                    'payment_status' => 'The submitted payment proof could not be found.',
                ]);
            }

            if (! in_array($decision, ['verified', 'rejected'], true)) {
                throw ValidationException::withMessages(['payment_status' => 'Choose a valid payment review decision.']);
            }
            if ($decision === 'rejected' && trim((string) $rejectionReason) === '') {
                throw ValidationException::withMessages(['rejection_reason' => 'A reason is required when rejecting a payment.']);
            }
            if ($lockedOrder->order_status === 'cancelled' && $decision === 'verified') {
                throw ValidationException::withMessages([
                    'payment_status' => 'A cancelled order cannot be marked as paid. Contact the customer before handling transferred funds.',
                ]);
            }

            $proof->status = $decision;
            $proof->reviewed_by = $admin->getKey();
            $proof->reviewed_at = now();
            $proof->rejection_reason = $decision === 'rejected' ? trim((string) $rejectionReason) : null;
            $proof->save();

            $lockedOrder->payment_status = $decision;
            $lockedOrder->save();

            return $proof->load(['reviewer', 'order.paymentMethod']);
        });
    }
}
