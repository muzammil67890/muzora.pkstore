<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function update(Order $order, string $status, ?string $trackingNumber): Order
    {
        return DB::transaction(function () use ($order, $status, $trackingNumber): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($status !== $lockedOrder->order_status && ! in_array($status, $lockedOrder->allowedNextStatuses(), true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'That status transition is not allowed from '.$lockedOrder->order_status.'.',
                ]);
            }

            if ($trackingNumber !== null && trim($trackingNumber) !== '' && ! in_array($status, ['shipped', 'delivered'], true)) {
                throw ValidationException::withMessages([
                    'tracking_number' => 'A tracking number can only be set for a shipped or delivered order.',
                ]);
            }

            if ($status === 'cancelled' && $lockedOrder->stock_restored_at === null) {
                $this->restoreStock($lockedOrder);
                $lockedOrder->stock_restored_at = now();
            }

            if ($status !== $lockedOrder->order_status) {
                $lockedOrder->order_status = $status;
                $timestampColumn = match ($status) {
                    'confirmed' => 'confirmed_at',
                    'processing' => 'processing_at',
                    'shipped' => 'shipped_at',
                    'delivered' => 'delivered_at',
                    'cancelled' => 'cancelled_at',
                    default => null,
                };

                if ($timestampColumn) {
                    $lockedOrder->{$timestampColumn} = now();
                }
            }

            if ($trackingNumber !== null) {
                $lockedOrder->tracking_number = trim($trackingNumber) !== '' ? trim($trackingNumber) : null;
            }

            $lockedOrder->save();

            return $lockedOrder->load('items');
        }, 3);
    }

    private function restoreStock(Order $order): void
    {
        $items = $order->items()
            ->whereNotNull('product_id')
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();

        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id')->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            if (! $product) {
                continue;
            }

            $quantity = (int) $item->quantity;
            $maximum = 4_294_967_295; // products.stock_quantity is an unsigned integer.
            if ((int) $product->stock_quantity > $maximum - $quantity) {
                throw ValidationException::withMessages([
                    'order_status' => 'Stock for '.$item->product_name.' cannot be restored safely; contact support.',
                ]);
            }

            Product::query()->whereKey($product->getKey())->increment('stock_quantity', $quantity);
        }
    }
}
