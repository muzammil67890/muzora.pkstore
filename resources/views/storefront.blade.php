<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141b2b">
    <title>{{ $storeSettings['store_name'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="announcement">{{ $storeSettings['shipping_free_threshold_minor'] > 0 ? 'Free nationwide delivery on orders over '.$storeSettings['currency_label'].' '.\App\Support\Money::formatDisplayMinor($storeSettings['shipping_free_threshold_minor']) : 'Free nationwide delivery' }} <span>&middot;</span> Official warranty on every order</div>
    <header class="site-header">
        <div class="page-width header-main">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><span class="material-symbols-outlined">bolt</span></span>{{ $storeSettings['store_name'] }}</a>
            <form class="search-box" action="{{ route('shop') }}" method="get"><input type="search" name="q" placeholder="Search headphones, watches, chargers..." aria-label="Search products"><button type="submit">Search</button></form>
            <div class="header-actions">@auth('web')<a href="{{ route('account.index') }}">Account</a><form action="{{ route('logout') }}" method="post">@csrf<button type="submit" class="header-text-button">Log out</button></form>@else<a href="{{ route('login') }}">Log in</a><a href="{{ route('register') }}">Register</a>@endauth<a href="{{ route('cart') }}">Cart <span data-cart-count @if(($cartCount ?? 0) < 1) hidden @endif>{{ $cartCount ?? 0 }}</span></a></div>
        </div>
        <nav class="page-width main-nav"><a href="{{ route('home') }}">Home</a><a href="{{ route('shop') }}">Shop all</a><a href="{{ route('shop', ['q' => 'audio']) }}">Audio</a><a href="{{ route('shop', ['q' => 'watch']) }}">Smart watches</a><a href="{{ route('shop', ['q' => 'gaming']) }}">Gaming</a><a href="{{ route('shop') }}#deals">Flash deals</a></nav>
    </header>
    @if (session('status'))<div class="page-width status-message" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="page-width error-message" role="alert">{{ $errors->first() }}</div>@endif

    @if (request()->routeIs('home'))
        <main>
            <section class="page-width hero">
                <div class="hero-copy"><span class="eyebrow"><span class="eyebrow-dot"></span> Pakistan's official tech store</span><h1>Latest tech.<br><span>Zero compromise.</span></h1><p>Discover verified smart gadgets and accessories at honest Pakistani prices. Every order comes with genuine local warranty and delivery across Pakistan.</p><div class="hero-buttons"><a class="button button-primary" href="{{ route('shop') }}">Shop the collection <span class="material-symbols-outlined">arrow_forward</span></a><a class="button button-light" href="#featured">Explore bestsellers</a></div><div class="hero-trust"><span><span class="material-symbols-outlined">verified</span> Official warranty</span><span><span class="material-symbols-outlined">local_shipping</span> Nationwide delivery</span><span><span class="material-symbols-outlined">task_alt</span> 100% original</span></div></div>
                <div class="hero-art" aria-label="Soundcore headphones illustration"><div class="hero-disc"></div><div class="hero-device"><span class="material-symbols-outlined">headphones</span></div><div class="floating-spec top"><span class="material-symbols-outlined">graphic_eq</span><span>Adaptive ANC<br><strong>Noise cancelled</strong></span></div><div class="floating-spec bottom"><span class="material-symbols-outlined">battery_charging_full</span><span>Up to <strong>40 hours</strong><br>playtime</span></div></div>
            </section>
            <section class="page-width section"><div class="section-heading"><div><span class="section-kicker">Find your next favorite</span><h2>Shop by category</h2><p>Good gear, sorted by what you need.</p></div><a class="text-link" href="{{ route('shop') }}">Browse all <span class="material-symbols-outlined">arrow_forward</span></a></div><div class="category-grid">@foreach ($categories as $category)<a class="category-tile" href="{{ route('shop', ['category' => $category[3]]) }}"><span class="category-icon"><span class="material-symbols-outlined">{{ $category[2] }}</span></span><span><strong>{{ $category[0] }}</strong><small>{{ $category[1] }}</small></span></a>@endforeach</div></section>
            <section class="page-width feature-strip"><div><span class="category-icon"><span class="material-symbols-outlined">local_shipping</span></span><span><strong>Fast delivery</strong><p>2-4 business days nationwide.</p></span></div><div><span class="category-icon"><span class="material-symbols-outlined">account_balance_wallet</span></span><span><strong>Pay your way</strong><p>Bank transfer and mobile wallet options.</p></span></div><div><span class="category-icon"><span class="material-symbols-outlined">verified</span></span><span><strong>Genuine gear</strong><p>Official local brand warranty.</p></span></div><div><span class="category-icon"><span class="material-symbols-outlined">support_agent</span></span><span><strong>Real support</strong><p>People ready to help, 7 days.</p></span></div></section>
            <section class="page-width section" id="featured"><div class="section-heading"><div><span class="section-kicker">Customer favorites</span><h2>Featured products</h2><p>Popular picks, checked and ready to ship.</p></div><a class="text-link" href="{{ route('shop') }}">View all products <span class="material-symbols-outlined">arrow_forward</span></a></div><div class="product-grid">@foreach (array_slice($products, 0, 4) as $product) @include('partials.product-card', ['product' => $product]) @endforeach</div></section>
            <section class="page-width section" id="deals"><div class="sale-banner"><div><span class="sale-tag">Limited time flash deals</span><h2>Big tech, better prices.</h2><p>Save on audio, fast chargers and smart wearables. Free delivery on qualifying orders, anywhere in Pakistan.</p></div><a class="button button-danger" href="{{ route('shop') }}">Shop the deals <span class="material-symbols-outlined">arrow_forward</span></a></div></section>
        </main>
    @elseif (request()->routeIs('shop'))
        <main class="page-width shop-layout">
            <aside class="filter-panel">
                <h2>Filters</h2>
                <div class="filter-group options-row">
                    <h3>Category</h3>
                    <a class="filter-option {{ empty($activeFilters['category']) ? 'selected' : '' }}" data-category-filter="all" href="{{ route('shop', request()->except(['category', 'page'])) }}">All products</a>
                    @foreach ($categories as $category)
                        <a class="filter-option {{ ($activeFilters['category'] ?? '') === $category->slug ? 'selected' : '' }}" data-category-filter="{{ $category->slug }}" href="{{ route('shop', array_merge(request()->except('page'), ['category' => $category->slug])) }}">{{ $category->name }}</a>
                    @endforeach
                </div>
                <form method="get" action="{{ route('shop') }}" class="filter-group">
                    @if (request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                    @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                    <h3>Availability</h3>
                    <input type="hidden" name="in_stock" value="0">
                    <label class="filter-option"><input type="checkbox" name="in_stock" value="1" @checked(($activeFilters['in_stock'] ?? '1') == '1') data-stock-filter> In stock</label>
                    <label class="filter-option"><input type="checkbox" name="on_sale" value="1" @checked(($activeFilters['on_sale'] ?? false) == '1') data-sale-filter> On sale</label>
                    <h3>Price range</h3>
                    <label class="filter-option"><input type="radio" name="max_price" value="" @checked(empty($activeFilters['max_price'])) data-price-filter> Any price</label>
                    <label class="filter-option"><input type="radio" name="max_price" value="20000" @checked(($activeFilters['max_price'] ?? '') == '20000') data-price-filter> Under {{ $storeSettings['currency_label'] }} 20,000</label>
                    <button class="button button-outline" type="submit">Apply filters</button>
                </form>
            </aside>
            <div>
                <div class="shop-toolbar">
                    <div><h1>Shop all products</h1><p>Verified tech, delivered across Pakistan.</p></div>
                    <form method="get" action="{{ route('shop') }}">
                        @foreach (request()->except('sort', 'page') as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                        <select class="sort-select" name="sort" aria-label="Sort products" onchange="this.form.submit()">
                            <option value="featured" @selected(($activeFilters['sort'] ?? 'featured') === 'featured')>Featured</option>
                            <option value="newest" @selected(($activeFilters['sort'] ?? '') === 'newest')>Newest</option>
                            <option value="price-asc" @selected(($activeFilters['sort'] ?? '') === 'price-asc')>Price: low to high</option>
                            <option value="price-desc" @selected(($activeFilters['sort'] ?? '') === 'price-desc')>Price: high to low</option>
                        </select>
                    </form>
                </div>
                <div class="product-grid shop-grid">
                    @forelse ($products as $product)
                        @include('partials.product-card', ['product' => $product])
                    @empty
                        <p>No products matched your filters. Try another search or category.</p>
                    @endforelse
                </div>
                @if ($products instanceof \Illuminate\Contracts\Pagination\Paginator || $products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    <div class="catalog-pagination">{{ $products->links() }}</div>
                @endif
            </div>
        </main>
    @elseif (request()->routeIs('product.show'))
        <main class="page-width"><div class="breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('shop') }}">Shop</a> / {{ $detail['category_name'] ?: 'Product' }} / {{ $detail['title'] }}</div><section class="product-detail"><div class="detail-art">@if ($detail['image'])<img src="{{ $detail['image'] }}" alt="{{ $detail['image_alt'] }}">@else<span class="material-symbols-outlined">{{ $detail['icon'] }}</span>@endif</div><div class="detail-copy"><span class="section-kicker">{{ $detail['brand'] }}</span><h1>{{ $detail['title'] }}</h1><div class="product-rating"><span class="material-symbols-outlined">star</span><strong>{{ $detail['rating'] }}</strong><small>({{ $detail['reviews'] }} reviews)</small></div><p>{{ $detail['description'] ?: 'Product details will be updated soon.' }}</p><span class="detail-stock">{{ $detail['stock'] > 0 ? 'In stock - Ready to ship' : 'Currently out of stock' }}</span><div class="detail-price"><strong>{{ $storeSettings['currency_label'] }} {{ $detail['price_formatted'] }}</strong>@if ($detail['old'])<del>{{ $storeSettings['currency_label'] }} {{ $detail['old_formatted'] }}</del>@endif @if ($detail['badge'])<span class="sale-tag">{{ $detail['badge'] }}</span>@endif</div><div class="detail-checks"><span><span class="material-symbols-outlined">verified</span> Genuine product</span><span><span class="material-symbols-outlined">local_shipping</span> Nationwide delivery</span><span><span class="material-symbols-outlined">payments</span> Secure manual payment options</span><span><span class="material-symbols-outlined">assignment_return</span> See store returns policy</span></div><form method="post" action="{{ route('cart.items.store') }}" class="product-cart-form" data-cart-add-form data-cart-url="{{ route('cart') }}">
                    @csrf
                    <input type="hidden" name="product" value="{{ $detail['id'] }}">
                    <input type="hidden" name="quantity" value="1" data-quantity-input>
                    <div class="quantity-control" data-quantity-control data-max-quantity="{{ max(1, $detail['stock']) }}"><button type="button" data-step="-1" aria-label="Decrease quantity" @disabled($detail['stock'] < 1)>-</button><output>1</output><button type="button" data-step="1" aria-label="Increase quantity" @disabled($detail['stock'] < 1)>+</button></div>
                    <div class="buy-row"><button class="button button-primary" type="submit" @disabled($detail['stock'] < 1)><span class="material-symbols-outlined">shopping_bag</span>Add to cart</button><button class="button button-outline" type="submit" name="buy_now" value="1" @disabled($detail['stock'] < 1)>Buy now</button></div>
                </form>
                <form method="post" action="{{ route('wishlist.store', ['product' => $detail['id']]) }}" class="wishlist-product-form">
                    @csrf
                    <button class="button button-outline" type="submit"><span class="material-symbols-outlined">favorite</span>Save to wishlist</button>
                </form></div></section>
            <section class="page-width product-reviews-section" id="reviews">
                <div class="section-heading"><div><span class="section-kicker">Verified customer feedback</span><h2>Product reviews</h2><p>{{ $detail['rating'] === '—' ? 'No approved ratings yet.' : $detail['rating'].' out of 5 from '.$detail['reviews'].' approved review(s).' }}</p></div></div>
                @if ($approvedReviews->isEmpty())
                    <div class="empty-state"><h3>No approved reviews yet</h3><p>Reviews from verified purchases appear here after moderation.</p></div>
                @else
                    <div class="approved-review-list">
                        @foreach ($approvedReviews as $review)
                            <article class="approved-review-card"><div class="approved-review-heading"><strong>{{ $review->title ?: 'Customer review' }}</strong><span>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }} · {{ $review->rating }}/5</span></div><p>{{ $review->body }}</p><small>{{ $review->user->name }} · {{ $review->created_at->format('M j, Y') }} · Verified purchase</small></article>
                        @endforeach
                    </div>
                    <div class="catalog-pagination">{{ $approvedReviews->links() }}</div>
                @endif
                @auth('web')
                    @foreach ($myProductReviews->whereIn('status', ['pending', 'rejected']) as $ownReview)
                        <div class="status-message">Your review for order {{ $ownReview->order?->order_number }} is {{ $ownReview->status }}.@if ($ownReview->moderation_note) {{ $ownReview->moderation_note }}@endif</div>
                    @endforeach
                    @if ($eligibleReviewOrders->isNotEmpty())
                        <section class="checkout-panel review-submit-panel"><h3>Review this product</h3><p>Only a delivered, payment-verified purchase is eligible. New reviews are held for moderation before publication.</p>
                            <form method="post" action="{{ route('reviews.store', ['product' => $detail['id']]) }}" class="review-form">
                                @csrf
                                <label>Delivered order<select name="order_id" required><option value="">Choose an order</option>@foreach ($eligibleReviewOrders as $eligibleOrder)<option value="{{ $eligibleOrder->id }}" @selected((string) old('order_id') === (string) $eligibleOrder->id)>{{ $eligibleOrder->order_number }} · {{ $eligibleOrder->created_at->format('M j, Y') }}</option>@endforeach</select></label>
                                <label>Rating<select name="rating" required><option value="">Choose a rating</option>@for ($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}" @selected((string) old('rating') === (string) $rating)>{{ $rating }} / 5</option>@endfor</select></label>
                                <label>Title (optional)<input name="title" value="{{ old('title') }}" maxlength="160"></label>
                                <label class="review-body-field">Your review<textarea name="body" rows="4" minlength="8" maxlength="4000" required>{{ old('body') }}</textarea></label>
                                @error('order_id')<p class="field-error">{{ $message }}</p>@enderror
                                @error('rating')<p class="field-error">{{ $message }}</p>@enderror
                                @error('body')<p class="field-error">{{ $message }}</p>@enderror
                                @error('review')<p class="field-error">{{ $message }}</p>@enderror
                                <button class="button button-primary" type="submit">Submit review</button>
                            </form>
                        </section>
                    @elseif ($myProductReviews->isEmpty())
                        <div class="empty-state review-eligibility-note"><h3>Purchased this product?</h3><p>You can leave a review after an order containing this product is delivered.</p></div>
                    @endif
                @else
                    <div class="empty-state review-eligibility-note"><p><a class="text-link" href="{{ route('login') }}">Log in</a> with the account that purchased this product to submit a review after delivery.</p></div>
                @endauth
            </section>
        </main>
    @elseif (request()->routeIs('checkout.create'))
        <main class="page-width checkout-page">
            <div class="breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('cart') }}">Shopping cart</a> / Secure checkout</div>
            <div class="shop-toolbar"><div><span class="section-kicker">Review and place order</span><h1>Checkout</h1><p>Order totals are calculated from current database prices and configured shipping.</p></div></div>
            @if ($checkoutQuote['issues'] !== [])
                <div class="error-message" role="alert"><strong>Please review your cart before placing this order:</strong><ul>@foreach ($checkoutQuote['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul><a class="text-link" href="{{ route('cart') }}">Return to cart</a></div>
            @endif
            <div class="checkout-layout">
                <section class="checkout-panel checkout-form-panel">
                    <h2>Delivery information</h2>
                    <form method="post" action="{{ route('checkout.store') }}" class="checkout-form">
                        @csrf
                        <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
                        <label>Full name<input type="text" name="customer_name" value="{{ old('customer_name', $checkoutUser->name) }}" maxlength="120" autocomplete="name" required></label>
                        <label>Email<input type="email" name="customer_email" value="{{ old('customer_email', $checkoutUser->email) }}" maxlength="255" autocomplete="email" required></label>
                        <label>Phone number<input type="tel" name="customer_phone" value="{{ old('customer_phone', $checkoutUser->phone) }}" maxlength="30" autocomplete="tel" required></label>
                        <label class="checkout-field-wide">Address<textarea name="shipping_address" rows="3" maxlength="2000" autocomplete="street-address" required>{{ old('shipping_address', $checkoutUser->address) }}</textarea></label>
                        <label>City<input type="text" name="city" value="{{ old('city') }}" maxlength="120" autocomplete="address-level2" required></label>
                        <label>Province<input type="text" name="province" value="{{ old('province') }}" maxlength="120" autocomplete="address-level1" required></label>
                        <label>Postal code<input type="text" name="postal_code" value="{{ old('postal_code') }}" maxlength="30" autocomplete="postal-code" required></label>
                        <label class="checkout-field-wide">Additional notes<textarea name="customer_notes" rows="3" maxlength="2000">{{ old('customer_notes') }}</textarea></label>
                        <label class="checkout-field-wide">Manual payment method
                            <select name="payment_method_id" required @disabled($paymentMethods->isEmpty())>
                                <option value="">Choose a payment method</option>
                                @foreach ($paymentMethods as $paymentMethod)
                                    <option value="{{ $paymentMethod->id }}" @selected((string) old('payment_method_id') === (string) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('payment_method_id')<p class="field-error checkout-field-wide">{{ $message }}</p>@enderror
                        @if ($paymentMethods->isEmpty())<p class="error-message checkout-field-wide" role="alert">Manual payment methods are not configured yet. Please contact customer support before placing an order.</p>@endif
                        <button class="button button-primary" type="submit" @disabled($checkoutQuote['issues'] !== [] || $checkoutQuote['coupon_error'] !== null || $paymentMethods->isEmpty())>Place order</button>
                    </form>
                </section>
                <aside class="checkout-panel checkout-order-summary">
                    <h2>Your order</h2>
                    <div class="checkout-summary-items">
                        @foreach ($checkoutQuote['items'] as $line)
                            <div class="checkout-summary-item"><span>{{ $line['product_name'] }} <small>× {{ $line['quantity'] }}</small></span><strong>{{ $storeSettings['currency_label'] }} {{ \App\Support\Money::formatMinor($line['line_total_minor']) }}</strong></div>
                        @endforeach
                    </div>
                    <div class="checkout-coupon-box">
                        <h3>Coupon code</h3>
                        @if ($checkoutQuote['coupon_error'])<p class="field-error" role="alert">{{ $checkoutQuote['coupon_error'] }}</p>@endif
                        @error('coupon_code')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        @if ($checkoutQuote['coupon'])<p class="status-message">{{ $checkoutQuote['coupon']->code }} applied. Save {{ $storeSettings['currency_label'] }} {{ \App\Support\Money::formatMinor($checkoutQuote['discount_minor']) }}.</p>@endif
                        <form method="post" action="{{ route('checkout.coupon.apply') }}" class="coupon-apply-form">@csrf<label class="sr-only" for="coupon-code">Coupon code</label><input id="coupon-code" name="coupon_code" value="{{ old('coupon_code', session('checkout.coupon_code')) }}" maxlength="64" placeholder="Enter coupon code" required><button class="button button-outline" type="submit">Apply</button></form>
                        @if (session('checkout.coupon_code'))<form method="post" action="{{ route('checkout.coupon.remove') }}" class="coupon-remove-form">@csrf @method('DELETE')<button class="text-link" type="submit">Remove coupon</button></form>@endif
                    </div>
                    <div class="summary-row"><span>Subtotal</span><strong>{{ $storeSettings['currency_label'] }} {{ \App\Support\Money::formatMinor($checkoutQuote['subtotal_minor']) }}</strong></div>
                    <div class="summary-row"><span>Shipping</span><strong>{{ $checkoutQuote['shipping_minor'] === 0 ? 'Free' : $storeSettings['currency_label'].' '.\App\Support\Money::formatMinor($checkoutQuote['shipping_minor']) }}</strong></div>
                    <div class="summary-row"><span>Discount</span><strong>- {{ $storeSettings['currency_label'] }} {{ \App\Support\Money::formatMinor($checkoutQuote['discount_minor']) }}</strong></div>
                    <div class="summary-row total"><span>Total</span><strong>{{ $storeSettings['currency_label'] }} {{ \App\Support\Money::formatMinor($checkoutQuote['total_minor']) }}</strong></div>
                    <p class="checkout-payment-note">Payment is manual. After placing your order, you will see the configured account instructions and can upload payment proof from the order details.</p>
                </aside>
            </div>
        </main>
    @else
        <main class="page-width">
            <div class="breadcrumb"><a href="{{ route('home') }}">Home</a> / Shopping cart</div>
            <div class="cart-layout">
                <section class="cart-panel">
                    <h1>Your cart <small data-cart-summary-count>{{ $cartCount }} item{{ $cartCount === 1 ? '' : 's' }}</small></h1>
                    @if ($cart->items->isEmpty())
                        <div class="empty-cart"><p>Your cart is waiting for something good.</p><a class="button button-primary" href="{{ route('shop') }}">Browse the catalog</a></div>
                    @else
                        @foreach ($cart->items as $cartItem)
                            @php($product = $cartItem->product)
                            <article class="cart-item">
                                <div class="cart-item-art">
                                    @if ($product->images->first())
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->images->first()->path) }}" alt="{{ $product->images->first()->alt_text ?: $product->name }}" loading="lazy">
                                    @else
                                        <span class="material-symbols-outlined">{{ $product->icon ?: 'devices_other' }}</span>
                                    @endif
                                </div>
                                <div>
                                    <h3><a href="{{ route('product.show', ['product' => $product->slug]) }}">{{ $product->name }}</a></h3>
                                    <small>{{ $storeSettings['currency_label'] }} {{ $cartItem->formattedUnitPrice() }} each</small>
                                    @if (!$product->is_active || $product->stock_quantity < 1)
                                        <small class="cart-unavailable">Currently unavailable</small>
                                    @elseif ($cartItem->quantity > $product->stock_quantity)
                                        <small class="cart-unavailable">Only {{ $product->stock_quantity }} currently available; adjust quantity.</small>
                                    @endif
                                </div>
                                <div class="cart-item-end">
                                    <strong>{{ $storeSettings['currency_label'] }} {{ $cartItem->formattedSubtotal() }}</strong>
                                    <div class="cart-item-tools">
                                        <form method="post" action="{{ route('cart.items.update', $cartItem) }}" data-cart-mutation-form>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ max(1, min($cartItem->quantity - 1, $product->stock_quantity)) }}">
                                            <button type="submit" aria-label="Decrease quantity" @disabled(!$product->is_active || $product->stock_quantity < 1 || $cartItem->quantity <= 1)>-</button>
                                        </form>
                                        <span>{{ $cartItem->quantity }}</span>
                                        <form method="post" action="{{ route('cart.items.update', $cartItem) }}" data-cart-mutation-form>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ $cartItem->quantity + 1 }}">
                                            <button type="submit" aria-label="Increase quantity" @disabled(!$product->is_active || $cartItem->quantity >= $product->stock_quantity)>+</button>
                                        </form>
                                        <form method="post" action="{{ route('cart.items.destroy', $cartItem) }}" data-cart-mutation-form>
                                            @csrf @method('DELETE')
                                            <button class="remove-item" type="submit" aria-label="Remove {{ $product->name }}">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    @endif
                    <a class="text-link" href="{{ route('shop') }}"><span class="material-symbols-outlined">arrow_back</span> Continue shopping</a>
                </section>
                <aside class="checkout-panel cart-summary-panel">
                    <h2>Cart subtotal</h2>
                    <div class="summary-row total"><span>Cart subtotal</span><span>{{ $storeSettings['currency_label'] }} {{ $cartSubtotalFormatted }}</span></div>
                    @if ($cart->items->isNotEmpty())
                        <form method="post" action="{{ route('cart.clear') }}" data-cart-mutation-form>@csrf<button class="button button-outline" type="submit">Clear cart</button></form>
                    @endif
                    @if ($cart->items->isNotEmpty())<a class="button button-dark" href="{{ route('checkout.create') }}">Proceed to secure checkout</a>@else<button class="button button-dark" type="button" disabled>Checkout</button>@endif
                </aside>
            </div>
        </main>
    @endif

    <footer class="site-footer"><div class="page-width footer-grid"><div><a class="brand" href="{{ route('home') }}"><span class="brand-mark"><span class="material-symbols-outlined">bolt</span></span>{{ $storeSettings['store_name'] }}</a><p>{{ $storeSettings['footer_text'] }}</p></div><div><h2>Shop</h2><a href="{{ route('shop') }}">All products</a><a href="{{ route('shop', ['q' => 'audio']) }}">Audio & sound</a><a href="{{ route('shop', ['q' => 'gaming']) }}">Gaming gear</a></div><div><h2>Customer care</h2><a href="{{ route('cart') }}">Delivery & returns</a><a href="{{ route('cart') }}">Warranty information</a><a href="{{ route('cart') }}">Contact support{{ $storeSettings['store_email'] ? ' · '.$storeSettings['store_email'] : '' }}</a></div><div><h2>We deliver nationwide</h2><p>Configured manual bank and mobile-wallet transfers<br>Official brand warranty@if ($storeSettings['store_phone'])<br>{{ $storeSettings['store_phone'] }}@endif @if ($storeSettings['store_address'])<br>{{ $storeSettings['store_address'] }}@endif</p></div></div><div class="page-width footer-bottom"><span>Copyright {{ date('Y') }} {{ $storeSettings['store_name'] }}</span><span>Prices shown in {{ $storeSettings['currency_label'] }}</span></div></footer>
    <div class="toast" data-toast role="status" aria-live="polite"><span data-toast-message>Added to cart</span></div>
</body>
</html>