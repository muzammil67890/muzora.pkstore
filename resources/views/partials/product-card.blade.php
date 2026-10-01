<article class="product-card" data-product-category="{{ $product['category'] }}" data-product-title="{{ $product['title'] }} {{ $product['brand'] }}" data-product-price="{{ $product['price'] }}" data-in-stock="{{ $product['in_stock'] ? 'true' : 'false' }}" data-on-sale="{{ $product['old'] ? 'true' : 'false' }}">
    <a class="product-visual {{ $product['icon'] }}" href="{{ route('product.show', ['product' => $product['id']]) }}" aria-label="View {{ $product['title'] }}">
        @if ($product['badge'])<span class="product-badge">{{ $product['badge'] }}</span>@endif
        <span class="product-badge warranty">Genuine product</span>
        @if ($product['image'])<img class="product-card-image" src="{{ $product['image'] }}" alt="{{ $product['image_alt'] }}" loading="lazy">@else<span class="product-icon material-symbols-outlined">{{ $product['icon'] }}</span>@endif
    </a>
    <div class="product-body">
        <span class="product-brand">{{ $product['brand'] }}</span>
        <a class="product-title" href="{{ route('product.show', ['product' => $product['id']]) }}">{{ $product['title'] }}</a>
        <div class="product-rating"><span class="material-symbols-outlined">star</span><strong>{{ $product['rating'] }}</strong><small>({{ $product['reviews'] }})</small></div>
        <div class="product-price"><strong>{{ $storeSettings['currency_label'] }} {{ $product['price_formatted'] }}</strong>@if ($product['old'])<del>{{ $storeSettings['currency_label'] }} {{ $product['old_formatted'] }}</del>@endif</div>
        <div class="product-actions">
            <form method="post" action="{{ route('cart.items.store') }}" data-cart-add-form>
                @csrf
                <input type="hidden" name="product" value="{{ $product['id'] }}">
                <input type="hidden" name="quantity" value="1">
                <button class="button button-primary" type="submit" @disabled(!$product['in_stock'])>{{ $product['in_stock'] ? 'Add' : 'Out of stock' }}</button>
            </form>
            <a class="button button-outline" href="{{ route('product.show', ['product' => $product['id']]) }}">View</a>
        </div>
    </div>
</article>
