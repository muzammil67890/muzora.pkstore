@extends('layouts.store')

@section('title', 'Payment review | MUZORA.PK Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>Payment verification</h1><p>Orders awaiting review of customer-submitted manual payment proofs.</p></div></div>
    <nav class="account-nav" aria-label="Admin navigation"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.orders.index') }}">Orders</a><a href="{{ route('admin.payments.index') }}">Payment review</a><a href="{{ route('admin.payment-methods.index') }}">Payment methods</a></nav>
    <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Order</th><th>Customer</th><th>Method</th><th>Amount</th><th>Submitted</th><th></th></tr></thead><tbody>
        @forelse ($orders as $order)
            @php($proof = $order->paymentProofs->first())
            <tr><td>{{ $order->order_number }}</td><td>{{ $order->customer_name }}<br><small>{{ $order->customer_email }}</small></td><td>{{ $order->paymentMethod?->name ?? 'Unavailable' }}</td><td>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->total_amount_minor) }}</td><td>{{ $proof?->submitted_at?->format('M j, Y g:i A') ?? '—' }}</td><td><a class="text-link" href="{{ route('admin.payments.show', $order) }}">Review order</a></td></tr>
        @empty
            <tr><td colspan="6">No submitted payments are awaiting review.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="catalog-pagination">{{ $orders->links() }}</div>
</section>
@endsection
