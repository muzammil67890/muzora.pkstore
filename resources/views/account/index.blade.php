@extends('layouts.store')

@section('title', 'My account | MUZORA.PK')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Customer account</span><h1>Welcome, {{ auth('web')->user()->name }}</h1><p>Manage your account details and browse account features.</p></div></div>
    <nav class="account-nav" aria-label="Account navigation">
        <a href="{{ route('account.profile.edit') }}">Profile</a>
        <a href="{{ route('account.orders.index') }}">My orders</a>
        <a href="{{ route('account.wishlist') }}">Wishlist</a>
        <a href="{{ route('account.password.edit') }}">Change password</a>
    </nav>
    <div class="account-cards">
        <a class="account-card" href="{{ route('account.profile.edit') }}"><span class="material-symbols-outlined">person</span><strong>Profile details</strong><small>Update your name, email, phone and address.</small></a>
        <a class="account-card" href="{{ route('account.orders.index') }}"><span class="material-symbols-outlined">package_2</span><strong>Orders</strong><small>View the orders placed from your account.</small></a>
        <a class="account-card" href="{{ route('account.wishlist') }}"><span class="material-symbols-outlined">favorite</span><strong>Wishlist</strong><small>Your saved products will appear here.</small></a>
    </div>
</section>
@endsection
