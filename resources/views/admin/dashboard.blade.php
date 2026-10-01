@extends('layouts.store')

@section('title', 'Admin dashboard | MUZORA.PK')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>Dashboard</h1><p>Current database counts only. Sales analytics are not included.</p></div></div>
    @include('admin.partials.navigation')
    <div class="account-cards admin-stats">
        <div class="account-card"><span class="material-symbols-outlined">inventory_2</span><strong>Total products</strong><small>{{ number_format($productCount) }} database records</small></div>
        <div class="account-card"><span class="material-symbols-outlined">category</span><strong>Total categories</strong><small>{{ number_format($categoryCount) }} database records</small></div>
        <div class="account-card"><span class="material-symbols-outlined">sell</span><strong>Total brands</strong><small>{{ number_format($brandCount) }} database records</small></div>
        <div class="account-card"><span class="material-symbols-outlined">group</span><strong>Total customers</strong><small>{{ number_format($customerCount) }} database records</small></div>
        <a class="account-card" href="{{ route('admin.orders.index') }}"><span class="material-symbols-outlined">receipt_long</span><strong>Total orders</strong><small>{{ number_format($orderCount) }} database records</small></a>
    </div>
    <div class="empty-state admin-note"><span class="material-symbols-outlined">info</span><h2>Sales analytics are not available yet</h2><p>Order records are available in the admin order list, and submitted manual payments can be reviewed from Payment verification. Sales reports and other analytics are not included.</p></div>
</section>
@endsection
