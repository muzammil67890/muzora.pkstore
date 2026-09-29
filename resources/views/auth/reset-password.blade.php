@extends('layouts.store')

@section('title', 'Choose a new password | MUZORA.PK')

@section('content')
<section class="auth-layout">
    <div class="checkout-panel auth-card">
        <span class="section-kicker">Account recovery</span>
        <h1>Choose a new password</h1>
        <form method="post" action="{{ route('password.store') }}" class="form-stack">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>Email address<input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></label>
            <label>New password<input type="password" name="password" required minlength="10" autocomplete="new-password"><small>At least 10 characters, including upper/lowercase letters and a number.</small></label>
            <label>Confirm new password<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
            <button class="button button-primary" type="submit">Reset password</button>
        </form>
    </div>
</section>
@endsection
