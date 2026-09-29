@extends('layouts.store')

@section('title', ($creating ? 'Create' : 'Edit').' coupon | '.$storeSettings['store_name'].' Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>{{ $creating ? 'Create coupon' : 'Edit '.$coupon->code }}</h1><p>Discount values are recalculated against current cart data at checkout.</p></div><a class="button button-outline" href="{{ route('admin.coupons.index') }}">Back to coupons</a></div>
    @include('admin.partials.navigation')
    @php
        $couponValue = $coupon->exists
            ? ($coupon->type === 'percentage' ? $coupon->value : \App\Support\Money::fromMinor($coupon->value))
            : '';
    @endphp
    <section class="checkout-panel settings-form-panel">
        <form method="post" action="{{ $creating ? route('admin.coupons.store') : route('admin.coupons.update', $coupon) }}" class="payment-proof-form settings-form">
            @csrf
            @unless ($creating) @method('PUT') @endunless
            <label>Coupon code<input name="code" value="{{ old('code', $coupon->code) }}" maxlength="64" pattern="[A-Za-z0-9_-]+" autocomplete="off" required></label>
            <label>Discount type<select name="type" required><option value="percentage" @selected(old('type', $coupon->type ?: 'percentage') === 'percentage')>Percentage</option><option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>Fixed amount</option></select></label>
            <label>Discount value <small>(percentage number, or amount in {{ $storeSettings['currency_label'] }})</small><input type="number" name="value" value="{{ old('value', $couponValue) }}" min="0.01" max="10000000000" step="0.01" required></label>
            <label>Minimum order amount <small>(optional, {{ $storeSettings['currency_label'] }})</small><input type="number" name="minimum_order_amount" value="{{ old('minimum_order_amount', $coupon->minimum_order_amount_minor === null ? '' : \App\Support\Money::fromMinor($coupon->minimum_order_amount_minor)) }}" min="0" step="0.01"></label>
            <label>Maximum discount <small>(optional, {{ $storeSettings['currency_label'] }})</small><input type="number" name="maximum_discount_amount" value="{{ old('maximum_discount_amount', $coupon->maximum_discount_amount_minor === null ? '' : \App\Support\Money::fromMinor($coupon->maximum_discount_amount_minor)) }}" min="0" step="0.01"></label>
            <label>Total usage limit <small>(blank means unlimited)</small><input type="number" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" min="1" step="1"></label>
            <label>Per-customer usage limit <small>(blank means unlimited)</small><input type="number" name="usage_limit_per_customer" value="{{ old('usage_limit_per_customer', $coupon->usage_limit_per_customer) }}" min="1" step="1"></label>
            <label>Starts at<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\\TH:i')) }}"></label>
            <label>Expires at<input type="datetime-local" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d\\TH:i')) }}"></label>
            <label class="payment-active-toggle"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $coupon->is_active ?? false))> Active and available for checkout</label>
            <p class="form-help">Uses: {{ number_format($coupon->used_count ?? 0) }}. Usage counters and order history are retained when editing a code.</p>
            <button class="button button-primary" type="submit">{{ $creating ? 'Create coupon' : 'Save coupon' }}</button>
        </form>
    </section>
</section>
@endsection
