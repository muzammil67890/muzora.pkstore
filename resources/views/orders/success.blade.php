@extends('layouts.store')

@section('title', 'Order confirmed | MUZORA.PK')

@section('content')
<section class="account-page order-success-page">
    <div class="section-heading"><div><span class="section-kicker">Order placed</span><h1>Thank you, {{ $order->customer_name }}</h1><p>Your order has been created successfully. Review the selected manual payment instructions and current verification state below.</p></div></div>
    <div class="order-status-strip"><span>Order number: <strong>{{ $order->order_number }}</strong></span><span>Order status: <strong>{{ ucfirst($order->order_status) }}</strong></span><span>Payment status: <strong>{{ ucfirst($order->payment_status) }}</strong></span></div>
    <div class="data-table-wrap order-items-table"><table class="data-table"><thead><tr><th>Product</th><th>SKU</th><th>Unit price</th><th>Quantity</th><th>Line total</th></tr></thead><tbody>
        @foreach ($order->items as $item)
            <tr><td>{{ $item->product_name }}</td><td>{{ $item->product_sku ?: '—' }}</td><td>{{ $order->currency }} {{ $item->formattedUnitPrice() }}</td><td>{{ $item->quantity }}</td><td>{{ $order->currency }} {{ $item->formattedLineTotal() }}</td></tr>
        @endforeach
    </tbody></table></div>
    <div class="order-lower-grid">
        <section class="checkout-panel"><h2>Shipping address</h2><p><strong>{{ $order->customer_name }}</strong><br>{{ $order->customer_phone }}<br>{{ $order->shipping_address }}<br>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}</p></section>
        <section class="checkout-panel order-total-panel"><h2>Order total</h2><div class="summary-row"><span>Subtotal</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->subtotal_minor) }}</strong></div><div class="summary-row"><span>Shipping</span><strong>{{ $order->shipping_amount_minor === 0 ? 'Free' : $order->currency.' '.\App\Support\Money::formatMinor($order->shipping_amount_minor) }}</strong></div>@if ($order->coupon_code)<div class="summary-row"><span>Coupon</span><strong>{{ $order->coupon_code }}</strong></div>@endif<div class="summary-row"><span>Discount</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->discount_amount_minor) }}</strong></div><div class="summary-row total"><span>Total</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->total_amount_minor) }}</strong></div></section>
    </div>
    @include('payments.customer-panel', ['order' => $order])
    <div class="order-success-actions"><a class="button button-primary" href="{{ route('account.orders.show', $order) }}">View order details</a><a class="button button-outline" href="{{ route('shop') }}">Continue shopping</a></div>
</section>
@endsection
