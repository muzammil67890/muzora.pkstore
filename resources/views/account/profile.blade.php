@extends('layouts.store')

@section('title', 'Profile | MUZORA.PK')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Your details</span><h1>Profile</h1><p>Keep your account contact information current.</p></div></div>
    <div class="checkout-panel account-panel">
        <form method="post" action="{{ route('account.profile.update') }}" class="form-stack">
            @csrf
            @method('PUT')
            <label>Full name<input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name"></label>
            <label>Email address<input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email"></label>
            <label>Phone number<input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel"></label>
            <label>Address<textarea name="address" rows="4" maxlength="2000" autocomplete="street-address">{{ old('address', $user->address) }}</textarea></label>
            <button class="button button-primary" type="submit">Save profile</button>
        </form>
    </div>
</section>
@endsection
