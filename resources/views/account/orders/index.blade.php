@extends('layouts.store')

@section('title', 'My orders | MUZORA.PK')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Order history</span><h1>My orders</h1><p>Orders placed from your account.</p></div></div>
    <nav class="account-nav" aria-label="Account navigation">
        <a href="{{ route('account.index') }}">My account</a>
        <a href="{{ route('account.orders.index') }}">My orders</a>
        <a href="{{ route('account.wishlist') }}">Wishlist</a>
    </nav>
    @if ($orders->isEmpty())
        <div class="empty-state"><span class="material-symbols-outlined">package_2</span><h2>No orders yet</h2><p>Your completed checkout orders will appear here.</p><a class="button button-primary" href="{{ route('shop') }}">Continue shopping</a></div>
    @else
        <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Order status</th><th>Payment status</th><th></th></tr></thead><tbody>
            @foreach ($orders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->created_at->format('M j, Y') }}</td>
                    <td>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->total_amount_minor) }}</td>
                    <td>{{ ucfirst($order->order_status) }}</td>
                    <td>{{ ucfirst($order->payment_status) }}</td>
                    <td><a class="text-link" href="{{ route('account.orders.show', $order) }}">View order</a></td>
                </tr>
            @endforeach
        </tbody></table></div>
        <div class="catalog-pagination">{{ $orders->links() }}</div>
    @endif
</section>
@endsection
