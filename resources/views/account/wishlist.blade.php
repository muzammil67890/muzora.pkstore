@extends('layouts.store')

@section('title', 'Wishlist | MUZORA.PK')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Saved products</span><h1>Wishlist <small>({{ $wishlistCount }})</small></h1><p>Products saved to your account.</p></div></div>
    <nav class="account-nav" aria-label="Account navigation">
        <a href="{{ route('account.index') }}">My account</a>
        <a href="{{ route('wishlist.index') }}">Wishlist</a>
        <a href="{{ route('account.orders.index') }}">My orders</a>
    </nav>

    @if ($wishlistItems->isEmpty())
        <div class="empty-state"><span class="material-symbols-outlined">favorite</span><h2>Your wishlist is empty</h2><p>Save a product from its detail page and it will appear here.</p><a class="button button-primary" href="{{ route('shop') }}">Browse products</a></div>
    @else
        <div class="wishlist-products">
            @foreach ($wishlistItems as $wishlistItem)
                @php($product = $wishlistItem->product)
                <article class="wishlist-product">
                    <a class="wishlist-product-visual" href="{{ route('product.show', ['product' => $product->slug]) }}">
                        @if ($product->images->first())
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->images->first()->path) }}" alt="{{ $product->images->first()->alt_text ?: $product->name }}" loading="lazy">
                        @else
                            <span class="material-symbols-outlined">{{ $product->icon ?: 'devices_other' }}</span>
                        @endif
                    </a>
                    <div class="wishlist-product-body">
                        <h2><a href="{{ route('product.show', ['product' => $product->slug]) }}">{{ $product->name }}</a></h2>
                        <p>{{ $storeSettings['currency_label'] }} {{ \App\Support\Money::formatDisplayMinor(\App\Support\Money::toMinor((string) $product->price)) }}</p>
                        @if (!$product->is_active || $product->stock_quantity < 1)
                            <p class="cart-unavailable">Currently unavailable</p>
                        @endif
                        <div class="wishlist-product-actions">
                            @if ($product->is_active && $product->stock_quantity > 0)
                                <form method="post" action="{{ route('wishlist.move-to-cart', ['product' => $product->slug]) }}">@csrf<button class="button button-primary" type="submit">Move to cart</button></form>
                            @else
                                <button class="button button-primary" type="button" disabled>Unavailable</button>
                            @endif
                            <form method="post" action="{{ route('wishlist.destroy', ['product' => $product->slug]) }}">@csrf @method('DELETE')<button class="button button-outline" type="submit">Remove</button></form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
