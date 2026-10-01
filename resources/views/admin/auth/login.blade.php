@extends('layouts.store')

@section('title', 'Admin sign in | MUZORA.PK')

@section('content')
<section class="auth-layout">
    <div class="checkout-panel auth-card">
        <span class="section-kicker">Secure administration</span>
        <h1>Admin sign in</h1>
        <p class="muted-copy">This sign-in is separate from customer accounts. Admin accounts are provisioned by an authorized server operator.</p>
        <form method="post" action="{{ route('admin.login.store') }}" class="form-stack">
            @csrf
            <label>Admin email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
            <label class="checkbox-line"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button class="button button-dark" type="submit">Sign in securely</button>
        </form>
        <div class="auth-links"><a href="{{ route('home') }}">Return to store</a></div>
    </div>
</section>
@endsection
