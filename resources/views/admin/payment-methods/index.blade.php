@extends('layouts.store')

@section('title', 'Payment methods | MUZORA.PK Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>Manual payment methods</h1><p>Configure available offline bank-transfer and mobile-wallet options for checkout.</p></div><a class="button button-primary" href="{{ route('admin.payment-methods.create') }}">Add payment method</a></div>
    <nav class="account-nav" aria-label="Admin navigation"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.orders.index') }}">Orders</a><a href="{{ route('admin.payments.index') }}">Payment review</a><a href="{{ route('admin.payment-methods.index') }}">Payment methods</a><a href="{{ route('admin.products.index') }}">Products</a></nav>
    <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Display order</th><th>Method</th><th>Type</th><th>Account details</th><th>Customer selectable</th><th></th></tr></thead><tbody>
        @forelse ($paymentMethods as $method)
            <tr><td>{{ $method->sort_order }}</td><td>{{ $method->name }}</td><td>{{ ucfirst(str_replace('_', ' ', $method->type)) }}</td><td>{{ $method->account_title ?: '—' }}<br>{{ $method->account_number ?: ($method->iban ?: ($method->mobile_number ?: '—')) }}</td><td>{{ $method->is_active ? 'Active' : 'Inactive' }}</td><td><a class="text-link" href="{{ route('admin.payment-methods.edit', $method) }}">Edit</a></td></tr>
        @empty
            <tr><td colspan="6">No manual payment methods have been configured. Add one of the four supported methods to enable checkout.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
