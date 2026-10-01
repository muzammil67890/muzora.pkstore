@extends('layouts.store')

@section('title', 'Coupons | '.$storeSettings['store_name'].' Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>Coupons</h1><p>Create discount codes and review recorded usage. Codes are checked again at checkout.</p></div><a class="button button-primary" href="{{ route('admin.coupons.create') }}">Create coupon</a></div>
    @include('admin.partials.navigation')
    <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Code</th><th>Discount</th><th>Validity</th><th>Usage</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse ($coupons as $coupon)
            <tr>
                <td><strong>{{ $coupon->code }}</strong></td>
                <td>{{ $coupon->type === 'percentage' ? $coupon->value.'%' : $storeSettings['currency_label'].' '.\App\Support\Money::formatMinor($coupon->value) }}<br><small>Minimum: {{ $coupon->minimum_order_amount_minor === null ? 'None' : $storeSettings['currency_label'].' '.\App\Support\Money::formatMinor($coupon->minimum_order_amount_minor) }}</small></td>
                <td>{{ $coupon->starts_at?->format('M j, Y H:i') ?? 'Any start' }}<br>{{ $coupon->expires_at?->format('M j, Y H:i') ?? 'No expiry' }}</td>
                <td>{{ number_format($coupon->used_count) }} recorded / {{ $coupon->usage_limit === null ? 'Unlimited' : number_format($coupon->usage_limit) }} total<br><small>{{ $coupon->usages_count }} usage rows</small></td>
                <td>{{ $coupon->is_active ? 'Active' : 'Inactive' }}</td>
                <td><a class="text-link" href="{{ route('admin.coupons.edit', $coupon) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6">No coupons have been created yet.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="catalog-pagination">{{ $coupons->links() }}</div>
</section>
@endsection
