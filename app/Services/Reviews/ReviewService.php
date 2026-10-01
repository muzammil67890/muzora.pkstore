<?php

namespace App\Services\Reviews;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /** @return Collection<int, Order> */
    public function eligibleOrders(User $user, Product $product): Collection
    {
        if ($user->reviews()->where('product_id', $product->getKey())->exists()) {
            return new Collection();
        }

        return $user->orders()
            ->where('order_status', 'delivered')
            ->where('payment_status', 'verified')
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->getKey()))
            ->latest('created_at')
            ->get();
    }

    /** @param array{order_id: int|string, rating: int|string, title?: ?string, body: string} $data */
    public function submit(User $user, Product $product, array $data): Review
    {
        return DB::transaction(function () use ($user, $product, $data): Review {
            $order = Order::query()->lockForUpdate()->find($data['order_id']);
            if (! $order
                || (int) $order->user_id !== (int) $user->getKey()
                || $order->order_status !== 'delivered'
                || $order->payment_status !== 'verified'
                || ! $order->items()->where('product_id', $product->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'order_id' => 'Choose a delivered, payment-verified order that contains this product.',
                ]);
            }

            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->first();
            if (! $lockedProduct || ! $lockedProduct->is_active) {
                throw ValidationException::withMessages(['review' => 'This product is no longer available for review.']);
            }

            $duplicate = Review::query()
                ->where('user_id', $user->getKey())
                ->where('product_id', $product->getKey())
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'review' => 'You have already submitted a review for this product.',
                ]);
            }

            return Review::query()->create([
                'user_id' => $user->getKey(),
                'product_id' => $product->getKey(),
                'order_id' => $order->getKey(),
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => trim($data['body']),
                'status' => 'pending',
            ]);
        });
    }

    public function moderate(Review $review, Admin $admin, string $status, ?string $note): Review
    {
        return DB::transaction(function () use ($review, $admin, $status, $note): Review {
            $lockedReview = Review::query()->lockForUpdate()->findOrFail($review->getKey());
            if (! in_array($status, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Choose a valid moderation state.']);
            }

            $lockedReview->status = $status;
            $lockedReview->moderated_by = $admin->getKey();
            $lockedReview->moderated_at = now();
            $lockedReview->moderation_note = trim((string) $note) ?: null;
            $lockedReview->save();

            return $lockedReview->load(['user', 'product', 'moderator']);
        });
    }
}
