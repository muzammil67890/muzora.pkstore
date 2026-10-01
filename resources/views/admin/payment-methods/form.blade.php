@extends('layouts.store')

@section('title', ($creating ? 'Add' : 'Edit').' payment method | MUZORA.PK Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>{{ $creating ? 'Add payment method' : 'Edit '.$paymentMethod->name }}</h1><p>Only the four approved manual payment methods can be configured.</p></div><a class="button button-outline" href="{{ route('admin.payment-methods.index') }}">Back to payment methods</a></div>
    <nav class="account-nav" aria-label="Admin navigation"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.orders.index') }}">Orders</a><a href="{{ route('admin.payments.index') }}">Payment review</a><a href="{{ route('admin.payment-methods.index') }}">Payment methods</a></nav>
    <section class="checkout-panel payment-method-form-panel">
        <form method="post" action="{{ $creating ? route('admin.payment-methods.store') : route('admin.payment-methods.update', $paymentMethod) }}" class="payment-proof-form">
            @csrf
            @unless ($creating) @method('PUT') @endunless
            @if ($creating)
                @if ($availableMethods === [])<p class="empty-state">All four supported methods already exist. Edit an existing method to update details or availability.</p>@endif
                <label>Supported method<select name="method" required @disabled($availableMethods === [])><option value="">Choose one</option>@foreach ($availableMethods as $slug => $identity)<option value="{{ $slug }}" @selected(old('method') === $slug)>{{ $identity['name'] }} ({{ ucfirst(str_replace('_', ' ', $identity['type'])) }})</option>@endforeach</select></label>
                @error('method')<p class="field-error">{{ $message }}</p>@enderror
            @else
                <p><strong>{{ $paymentMethod->name }}</strong> · {{ ucfirst(str_replace('_', ' ', $paymentMethod->type)) }}</p>
            @endif
            <label>Account title (bank methods)<input type="text" name="account_title" value="{{ old('account_title', $paymentMethod->account_title ?? '') }}" maxlength="120"></label>
            @error('account_title')<p class="field-error">{{ $message }}</p>@enderror
            <label>Account number<input type="text" name="account_number" value="{{ old('account_number', $paymentMethod->account_number ?? '') }}" maxlength="100" autocomplete="off"></label>
            @error('account_number')<p class="field-error">{{ $message }}</p>@enderror
            <label>IBAN (optional if account number is provided)<input type="text" name="iban" value="{{ old('iban', $paymentMethod->iban ?? '') }}" maxlength="34" autocomplete="off"></label>
            @error('iban')<p class="field-error">{{ $message }}</p>@enderror
            <label>Mobile number (wallet methods)<input type="text" name="mobile_number" value="{{ old('mobile_number', $paymentMethod->mobile_number ?? '') }}" maxlength="30" autocomplete="off"></label>
            @error('mobile_number')<p class="field-error">{{ $message }}</p>@enderror
            <label>Customer-facing instructions<textarea name="instructions" rows="5" maxlength="4000">{{ old('instructions', $paymentMethod->instructions ?? '') }}</textarea></label>
            @error('instructions')<p class="field-error">{{ $message }}</p>@enderror
            <label>Display order<input type="number" name="sort_order" value="{{ old('sort_order', $paymentMethod->sort_order ?? 0) }}" min="0" max="65535" required></label>
            @error('sort_order')<p class="field-error">{{ $message }}</p>@enderror
            <label class="payment-active-toggle"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $paymentMethod->is_active ?? false))> Active and selectable at checkout</label>
            @error('is_active')<p class="field-error">{{ $message }}</p>@enderror
            <button class="button button-primary" type="submit" @disabled($creating && $availableMethods === [])>{{ $creating ? 'Create method' : 'Save method' }}</button>
        </form>
    </section>
</section>
@endsection
