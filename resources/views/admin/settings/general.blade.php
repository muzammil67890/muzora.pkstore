@extends('layouts.store')

@section('title', 'Store settings | '.$storeSettings['store_name'].' Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>General store settings</h1><p>These saved values are displayed on the storefront and used for future order currency snapshots.</p></div></div>
    @include('admin.partials.navigation')
    <section class="checkout-panel settings-form-panel">
        <form method="post" action="{{ route('admin.settings.general.update') }}" class="payment-proof-form settings-form">
            @csrf @method('PUT')
            <label>Store name<input name="store_name" value="{{ old('store_name', $settings['store_name']) }}" maxlength="120" required></label>
            <label>Store email<input type="email" name="store_email" value="{{ old('store_email', $settings['store_email']) }}" maxlength="255"></label>
            <label>Store phone<input type="tel" name="store_phone" value="{{ old('store_phone', $settings['store_phone']) }}" maxlength="30" placeholder="+92 300 1234567"></label>
            <label>Store address<textarea name="store_address" rows="3" maxlength="1000">{{ old('store_address', $settings['store_address']) }}</textarea></label>
            <label>Store currency<select name="currency" required>@foreach (['PKR' => 'Pakistani rupee', 'USD' => 'US dollar', 'EUR' => 'Euro', 'GBP' => 'Pound sterling', 'AED' => 'UAE dirham', 'SAR' => 'Saudi riyal'] as $code => $name)<option value="{{ $code }}" @selected(old('currency', $settings['currency']) === $code)>{{ $code }} — {{ $name }}</option>@endforeach</select></label>
            <label>Footer text<textarea name="footer_text" rows="3" maxlength="500">{{ old('footer_text', $settings['footer_text']) }}</textarea></label>
            <button class="button button-primary" type="submit">Save general settings</button>
        </form>
    </section>
</section>
@endsection
