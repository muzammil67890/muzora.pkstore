@extends('layouts.store')

@section('title', 'Create account | MUZORA.PK')

@section('content')
<section class="auth-layout">
    <div class="checkout-panel auth-card">
        <span class="section-kicker">Customer account</span>
        <h1>Create your account</h1>
        <p class="muted-copy">Register to manage your profile and access customer features.</p>
        <form method="post" action="{{ route('register.store') }}" class="form-stack">
            @csrf
            <label>Full name<input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
            <label>Phone number<input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="30" autocomplete="tel" placeholder="03XX XXXXXXX"></label>
            <label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password"><small>At least 10 characters, including upper/lowercase letters and a number.</small></label>
            <label>Confirm password<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
            <button class="button button-primary" type="submit">Create account</button>
        </form>
        <div class="auth-links"><span>Already registered? <a href="{{ route('login') }}">Log in</a></span></div>
    </div>
</section>
@endsection
