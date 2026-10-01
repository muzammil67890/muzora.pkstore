@extends('layouts.store')

@section('title', 'Orders | MUZORA.PK Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>Orders</h1><p>Search and review customer orders.</p></div></div>
    <nav class="account-nav" aria-label="Admin navigation"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.orders.index') }}">Orders</a><a href="{{ route('admin.payments.index') }}">Payment review</a><a href="{{ route('admin.payment-methods.index') }}">Payment methods</a><a href="{{ route('admin.products.index') }}">Products</a><a href="{{ route('admin.categories.index') }}">Categories</a><a href="{{ route('admin.brands.index') }}">Brands</a></nav>
    <form method="get" action="{{ route('admin.orders.index') }}" class="order-filter-form">
        <label>Order number<input type="search" name="order_number" value="{{ $filters['order_number'] ?? '' }}"></label>
        <label>Customer name, email or phone<input type="search" name="customer" value="{{ $filters['customer'] ?? '' }}"></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach (\App\Models\Order::ORDER_STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label>From date<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>To date<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button class="button button-primary" type="submit">Filter orders</button>
    </form>
    <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Order status</th><th>Payment status</th><th></th></tr></thead><tbody>
        @forelse ($orders as $order)
            <tr><td>{{ $order->order_number }}</td><td>{{ $order->customer_name }}<br><small>{{ $order->customer_email }}</small></td><td>{{ $order->created_at->format('M j, Y') }}</td><td>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->total_amount_minor) }}</td><td>{{ ucfirst($order->order_status) }}</td><td>{{ ucfirst($order->payment_status) }}</td><td><a class="text-link" href="{{ route('admin.orders.show', $order) }}">View</a></td></tr>
        @empty
            <tr><td colspan="7">No orders match these filters.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="catalog-pagination">{{ $orders->links() }}</div>
</section>
@endsection
