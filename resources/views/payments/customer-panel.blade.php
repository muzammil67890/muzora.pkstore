<section class="checkout-panel customer-payment-panel">
    <div class="payment-panel-heading"><div><span class="section-kicker">Manual payment</span><h2>Payment information</h2></div><strong class="payment-state-badge payment-state-{{ $order->payment_status }}">{{ ucfirst($order->payment_status) }}</strong></div>
    <div class="payment-instructions-grid">
        <div><span>Selected method</span><strong>{{ $order->paymentMethod?->name ?? 'Unavailable' }}</strong></div>
        <div><span>Amount to pay</span><strong>{{ $order->currency }} {{ \App\Support\Money::formatMinor($order->total_amount_minor) }}</strong></div>
        @if ($order->paymentMethod?->account_title)<div><span>Account title</span><strong>{{ $order->paymentMethod->account_title }}</strong></div>@endif
        @if ($order->paymentMethod?->account_number)<div><span>Account number</span><strong>{{ $order->paymentMethod->account_number }}</strong></div>@endif
        @if ($order->paymentMethod?->iban)<div><span>IBAN</span><strong>{{ $order->paymentMethod->iban }}</strong></div>@endif
        @if ($order->paymentMethod?->mobile_number)<div><span>Mobile number</span><strong>{{ $order->paymentMethod->mobile_number }}</strong></div>@endif
    </div>
    @if ($order->paymentMethod?->instructions)<p class="payment-instructions-copy">{{ $order->paymentMethod->instructions }}</p>@endif

    @foreach ($order->paymentProofs as $proof)
        <article class="payment-proof-card">
            <div class="payment-proof-heading"><strong>Proof submitted {{ $proof->submitted_at->format('M j, Y g:i A') }}</strong><span>{{ ucfirst($proof->status) }}</span></div>
            @if ($proof->transaction_id)<p>Transaction ID: <strong>{{ $proof->transaction_id }}</strong></p>@endif
            @if ($proof->notes)<p>Notes: {{ $proof->notes }}</p>@endif
            @if ($proof->rejection_reason)<p class="payment-rejection-reason"><strong>Admin feedback:</strong> {{ $proof->rejection_reason }}</p>@endif
            <a class="text-link" href="{{ route('account.orders.payment-proofs.download', [$order, $proof]) }}">Download submitted screenshot</a>
        </article>
    @endforeach

    @if ($order->payment_status === 'verified')
        <p class="payment-state-message">Your manual payment has been verified. Order fulfillment remains managed separately from payment status.</p>
    @elseif ($order->payment_status === 'submitted')
        <p class="payment-state-message">Your proof is submitted and waiting for administrator review. You cannot replace it while review is in progress.</p>
    @elseif ($order->order_status === 'cancelled')
        <p class="payment-state-message">This order is cancelled, so payment proof submission is unavailable. Contact support if you already transferred funds.</p>
    @elseif (! $order->paymentMethod)
        <p class="payment-state-message">No manual payment method is linked to this order. Please contact customer support before transferring funds.</p>
    @else
        <form method="post" action="{{ route('account.orders.payment-proof.store', $order) }}" enctype="multipart/form-data" class="payment-proof-form">
            @csrf
            <h3>{{ $order->payment_status === 'rejected' ? 'Submit corrected payment proof' : 'Submit payment proof' }}</h3>
            <p>Transfer the exact order total above using the instructions, then upload a screenshot. Transaction ID is optional.</p>
            <label>Transaction ID (optional)<input type="text" name="transaction_id" value="{{ old('transaction_id') }}" maxlength="120" autocomplete="off"></label>
            @error('transaction_id')<p class="field-error">{{ $message }}</p>@enderror
            <label>Payment screenshot<input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required></label>
            @error('screenshot')<p class="field-error">{{ $message }}</p>@enderror
            <label>Notes (optional)<textarea name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea></label>
            @error('notes')<p class="field-error">{{ $message }}</p>@enderror
            @error('payment_proof')<p class="field-error">{{ $message }}</p>@enderror
            <button class="button button-primary" type="submit">Submit for verification</button>
        </form>
    @endif
</section>
