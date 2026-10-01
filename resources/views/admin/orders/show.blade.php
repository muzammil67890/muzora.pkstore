@extends('layouts.store')

@section('title', $order->order_number.' | MUZORA.PK Admin')

@section('content')
<section class="account-page order-detail-page">
    <div class="section-heading"><div><span class="section-kicker">Order administration</span><h1>{{ $order->order_number }}</h1><p>Placed {{ $order->created_at->format('M j, Y g:i A') }}</p></div><a class="button button-outline" href="{{ route('admin.orders.index') }}">Back to orders</a></div>
    <div class="order-status-strip"><span>Order status: <strong>{{ ucfirst($order->order_status) }}</strong></span><span>Payment status: <strong>{{ ucfirst($order->payment_status) }}</strong></span></div>
    <div class="order-admin-grid">
        <section class="checkout-panel"><h2>Customer</h2><p><strong>{{ $order->customer_name }}</strong><br>{{ $order->customer_email }}<br>{{ $order->customer_phone }}</p><h2>Shipping address</h2><p>{{ $order->shipping_address }}<br>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}</p>@if ($order->customer_notes)<h2>Customer notes</h2><p>{{ $order->customer_notes }}</p>@endif</section>
        <section class="checkout-panel order-total-panel"><h2>Order total</h2><div class="summary-row"><span>Subtotal</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->subtotal_minor) }}</strong></div><div class="summary-row"><span>Shipping</span><strong>{{ $order->shipping_amount_minor === 0 ? 'Free' : $order->currency.' '.\App\Support\Money::formatMinor($order->shipping_amount_minor) }}</strong></div>@if ($order->coupon_code)<div class="summary-row"><span>Coupon</span><strong>{{ $order->coupon_code }}</strong></div>@endif<div class="summary-row"><span>Discount</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->discount_amount_minor) }}</strong></div><div class="summary-row total"><span>Total</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->total_amount_minor) }}</strong></div></section>
    </div>
    <section class="checkout-panel admin-payment-panel">
        <div class="payment-panel-heading"><div><span class="section-kicker">Manual payment review</span><h2>Payment details</h2></div><strong class="payment-state-badge payment-state-{{ $order->payment_status }}">{{ ucfirst($order->payment_status) }}</strong></div>
        <div class="payment-instructions-grid">
            <div><span>Selected method</span><strong>{{ $order->paymentMethod?->name ?? 'Unavailable' }}</strong></div>
            <div><span>Authoritative order total</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->total_amount_minor) }}</strong></div>
            @if ($order->paymentMethod?->account_title)<div><span>Account title</span><strong>{{ $order->paymentMethod->account_title }}</strong></div>@endif
            @if ($order->paymentMethod?->account_number)<div><span>Account number</span><strong>{{ $order->paymentMethod->account_number }}</strong></div>@endif
            @if ($order->paymentMethod?->iban)<div><span>IBAN</span><strong>{{ $order->paymentMethod->iban }}</strong></div>@endif
            @if ($order->paymentMethod?->mobile_number)<div><span>Mobile number</span><strong>{{ $order->paymentMethod->mobile_number }}</strong></div>@endif
        </div>
        @foreach ($order->paymentProofs as $proof)
            <article class="payment-proof-card">
                <div class="payment-proof-heading"><strong>Submitted {{ $proof->submitted_at->format('M j, Y g:i A') }}</strong><span>{{ ucfirst($proof->status) }}</span></div>
                <p>Customer: {{ $proof->user?->name ?? $order->customer_name }} ({{ $proof->user?->email ?? $order->customer_email }})</p>
                @if ($proof->transaction_id)<p>Transaction ID: <strong>{{ $proof->transaction_id }}</strong></p>@endif
                @if ($proof->amount_minor !== null)<p>Submitted order amount snapshot: {{ $order->currency }} {{ \App\Support\Money::formatMinor($proof->amount_minor) }}</p>@endif
                @if ($proof->notes)<p>Notes: {{ $proof->notes }}</p>@endif
                @if ($proof->rejection_reason)<p class="payment-rejection-reason"><strong>Rejection reason:</strong> {{ $proof->rejection_reason }}</p>@endif
                @if ($proof->reviewer)<p>Reviewed by {{ $proof->reviewer->name }} at {{ $proof->reviewed_at?->format('M j, Y g:i A') }}</p>@endif
                <a class="text-link" href="{{ route('admin.payments.proofs.download', [$order, $proof]) }}">Download private screenshot</a>
            </article>
        @endforeach
        @if ($order->payment_status === 'submitted')
            <div class="payment-review-actions">
                <form method="post" action="{{ route('admin.payments.review', $order) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="verified"><button class="button button-primary" type="submit">Verify payment</button></form>
                <form method="post" action="{{ route('admin.payments.review', $order) }}" class="payment-reject-form">@csrf @method('PATCH')<input type="hidden" name="decision" value="rejected"><label>Rejection reason<textarea name="rejection_reason" rows="2" maxlength="2000" required>{{ old('rejection_reason') }}</textarea></label>@error('rejection_reason')<p class="field-error">{{ $message }}</p>@enderror<button class="button button-outline" type="submit">Reject payment</button></form>
            </div>
        @elseif ($order->payment_status === 'verified')
            <p class="payment-state-message">Verified. This action did not automatically change the order fulfillment status.</p>
        @elseif ($order->payment_status === 'rejected')
            <p class="payment-state-message">Rejected. The customer can submit corrected proof from their order page.</p>
        @endif
        @error('payment_status')<p class="field-error">{{ $message }}</p>@enderror
    </section>
    <h2 class="order-section-title">Items</h2>
    <div class="data-table-wrap order-items-table"><table class="data-table"><thead><tr><th>Product snapshot</th><th>SKU snapshot</th><th>Unit price</th><th>Quantity</th><th>Line total</th></tr></thead><tbody>
        @foreach ($order->items as $item)
            <tr><td>{{ $item->product_name }}</td><td>{{ $item->product_sku ?: '—' }}</td><td>{{ $order->currency }} {{ $item->formattedUnitPrice() }}</td><td>{{ $item->quantity }}</td><td>{{ $order->currency }} {{ $item->formattedLineTotal() }}</td></tr>
        @endforeach
    </tbody></table></div>
    <section class="checkout-panel admin-order-status-panel"><h2>Update order status</h2><p>Allowed next statuses: {{ $nextStatuses === [] ? 'No further status transitions' : implode(', ', $nextStatuses) }}. Cancelling restores the purchased quantities to stock once.</p>
        <form method="post" action="{{ route('admin.orders.status.update', $order) }}" class="order-status-form">
            @csrf @method('PATCH')
            <label>Order status<select name="order_status">@foreach (array_unique(array_merge([$order->order_status], $nextStatuses)) as $status)<option value="{{ $status }}" @selected(old('order_status', $order->order_status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
            <label>Tracking number<input type="text" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}" maxlength="120" placeholder="Optional; set when shipped"></label>
            <button class="button button-primary" type="submit">Save status</button>
        </form>
    </section>
</section>
@endsection
