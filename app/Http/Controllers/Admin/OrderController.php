<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Orders\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderStatusService $statuses)
    {
    }

    public function index(OrderIndexRequest $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'adminViewAny', Order::class);
        $filters = $request->validated();
        $query = Order::query()->latest('created_at');

        if (! empty($filters['order_number'])) {
            $query->where('order_number', 'like', '%'.$filters['order_number'].'%');
        }

        if (! empty($filters['customer'])) {
            $search = $filters['customer'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_email', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('order_status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return view('admin.orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeForUser($request->user('admin'), 'adminView', $order);
        $order->load(['items', 'paymentMethod', 'paymentProofs.reviewer', 'paymentProofs.user']);

        return view('admin.orders.show', [
            'order' => $order,
            'nextStatuses' => $order->allowedNextStatuses(),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'adminUpdate', $order);
        $data = $request->safe()->only(['order_status', 'tracking_number']);
        $this->statuses->update($order, $data['order_status'], $data['tracking_number'] ?? null);

        return back()->with('status', 'Order status has been updated.');
    }
}
