<nav class="account-nav" aria-label="Admin navigation">
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a href="{{ route('admin.orders.index') }}">Orders</a>
    <a href="{{ route('admin.payments.index') }}">Payment review</a>
    <a href="{{ route('admin.payment-methods.index') }}">Payment methods</a>
    <a href="{{ route('admin.coupons.index') }}">Coupons</a>
    <a href="{{ route('admin.reviews.index') }}">Reviews</a>
    <a href="{{ route('admin.settings.general') }}">Store settings</a>
    <a href="{{ route('admin.settings.shipping') }}">Shipping settings</a>
    <a href="{{ route('admin.products.index') }}">Products</a>
    <a href="{{ route('admin.categories.index') }}">Categories</a>
    <a href="{{ route('admin.brands.index') }}">Brands</a>
</nav>
