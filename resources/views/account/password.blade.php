@extends('layouts.store')

@section('title', 'Change password | MUZORA.PK')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Account security</span><h1>Change password</h1><p>Confirm your current password before choosing a new one.</p></div></div>
    <div class="checkout-panel account-panel">
        <form method="post" action="{{ route('account.password.update') }}" class="form-stack">
            @csrf
            @method('PUT')
            <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>New password<input type="password" name="password" required minlength="10" autocomplete="new-password"><small>At least 10 characters, including upper/lowercase letters and a number.</small></label>
            <label>Confirm new password<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
            <button class="button button-primary" type="submit">Update password</button>
        </form>
    </div>
</section>
@endsection
