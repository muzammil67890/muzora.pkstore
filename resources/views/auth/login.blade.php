@extends('layouts.store')

@section('title', 'Log in | MUZORA.PK')

@section('content')
<section class="auth-layout">
    <div class="checkout-panel auth-card">
        <span class="section-kicker">Welcome back</span>
        <h1>Log in to your account</h1>
        <p class="muted-copy">View your profile and keep your account details up to date.</p>
        <form method="post" action="{{ route('login.store') }}" class="form-stack">
            @csrf
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
            <label class="checkbox-line"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button class="button button-primary" type="submit">Log in</button>
        </form>
        <div class="auth-links"><a href="{{ route('password.request') }}">Forgot password?</a><span>New to MUZORA.PK? <a href="{{ route('register') }}">Create an account</a></span></div>
    </div>
</section>
@endsection
