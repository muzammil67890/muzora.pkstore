@extends('layouts.store')

@section('title', 'Reset password | MUZORA.PK')

@section('content')
<section class="auth-layout">
    <div class="checkout-panel auth-card">
        <span class="section-kicker">Account recovery</span>
        <h1>Forgot your password?</h1>
        <p class="muted-copy">Enter your email address. If an account exists, we will send password reset instructions.</p>
        <form method="post" action="{{ route('password.email') }}" class="form-stack">
            @csrf
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <button class="button button-primary" type="submit">Send reset link</button>
        </form>
        <div class="auth-links"><a href="{{ route('login') }}">Back to login</a></div>
    </div>
</section>
@endsection
