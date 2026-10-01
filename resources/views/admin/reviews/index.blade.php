@extends('layouts.store')

@section('title', 'Review moderation | '.$storeSettings['store_name'].' Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Administration</span><h1>Review moderation</h1><p>Only approved reviews appear in public product ratings and review lists.</p></div></div>
    @include('admin.partials.navigation')
    <form method="get" action="{{ route('admin.reviews.index') }}" class="order-filter-form review-filter-form">
        <label>Moderation status<select name="status"><option value="pending" @selected($status === 'pending')>Pending</option><option value="approved" @selected($status === 'approved')>Approved</option><option value="rejected" @selected($status === 'rejected')>Rejected</option><option value="all" @selected($status === 'all')>All reviews</option></select></label>
        <button class="button button-outline" type="submit">Filter</button>
    </form>
    <div class="admin-review-list">
        @forelse ($reviews as $review)
            <article class="checkout-panel admin-review-card">
                <div class="admin-review-header"><div><span class="section-kicker">{{ $review->product->name }}</span><h2>{{ $review->title ?: 'Customer review' }}</h2><p>{{ $review->user->name }} · {{ $review->user->email }} · Order {{ $review->order->order_number }}</p></div><span class="review-status review-status-{{ $review->status }}">{{ ucfirst($review->status) }}</span></div>
                <p class="admin-review-stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }} · {{ $review->rating }}/5</p>
                <blockquote>{{ $review->body }}</blockquote>
                <small>Submitted {{ $review->created_at->format('M j, Y H:i') }}@if ($review->moderated_at) · moderated {{ $review->moderated_at->format('M j, Y H:i') }} by {{ $review->moderator?->name ?? 'an administrator' }}@endif</small>
                @if ($review->moderation_note)<p class="review-moderation-note">Previous note: {{ $review->moderation_note }}</p>@endif
                <form method="post" action="{{ route('admin.reviews.moderate', $review) }}" class="review-moderation-form">
                    @csrf @method('PATCH')
                    <label>Moderation note (optional)<textarea name="moderation_note" rows="2" maxlength="2000">{{ old('moderation_note') }}</textarea></label>
                    <div class="review-moderation-actions">
                        <button class="button button-primary" type="submit" name="status" value="approved">Approve</button>
                        <button class="button button-outline" type="submit" name="status" value="rejected">Reject</button>
                    </div>
                </form>
            </article>
        @empty
            <div class="empty-state"><h2>No {{ $status === 'all' ? '' : $status }} reviews found</h2><p>Customer submissions will appear here for moderation.</p></div>
        @endforelse
    </div>
    <div class="catalog-pagination">{{ $reviews->links() }}</div>
</section>
@endsection
