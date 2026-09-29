@extends('layouts.store')

@section('title', 'Shipping settings | '.$storeSettings['store_name'].' Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>Shipping and order thresholds</h1><p>Enter amounts in {{ $storeSettings['currency'] }}. They are stored and calculated as integer minor units.</p></div></div>
    @include('admin.partials.navigation')
    <section class="checkout-panel settings-form-panel">
        <form method="post" action="{{ route('admin.settings.shipping.update') }}" class="payment-proof-form settings-form">
            @csrf @method('PUT')
            <label>Standard shipping fee ({{ $storeSettings['currency'] }})<input type="number" name="shipping_standard_fee" value="{{ old('shipping_standard_fee', $settings['shipping_standard_fee']) }}" min="0" step="0.01" required></label>
            <label>Free shipping from subtotal ({{ $storeSettings['currency'] }})<input type="number" name="shipping_free_threshold" value="{{ old('shipping_free_threshold', $settings['shipping_free_threshold']) }}" min="0" step="0.01" required></label>
            <label>Minimum order subtotal ({{ $storeSettings['currency'] }})<input type="number" name="minimum_order_amount" value="{{ old('minimum_order_amount', $settings['minimum_order_amount']) }}" min="0" step="0.01" required></label>
            <p class="form-help">Until saved, shipping defaults use the environment's existing standard fee and free-shipping threshold. A threshold of zero means free shipping for every non-empty cart.</p>
            <button class="button button-primary" type="submit">Save shipping settings</button>
        </form>
    </section>
</section>
@endsection
