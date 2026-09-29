@extends('layouts.store')

@section('title', 'Products | MUZORA.PK Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Catalog</span><h1>Products</h1><p>Read-only catalog access for the authentication foundation phase.</p></div></div>
    <nav class="account-nav" aria-label="Admin catalog navigation"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.categories.index') }}">Categories</a><a href="{{ route('admin.brands.index') }}">Brands</a></nav>
    <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Brand</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead><tbody>
        @forelse ($products as $product)
            <tr><td>{{ $product->name }}</td><td>{{ $product->sku ?: '—' }}</td><td>{{ $product->category?->name ?: '—' }}</td><td>{{ $product->brand?->name ?: '—' }}</td><td>{{ $storeSettings['currency_label'] }} {{ \App\Support\Money::formatDisplayMinor(\App\Support\Money::toMinor((string) $product->price)) }}</td><td>{{ $product->stock_quantity }}</td><td>{{ $product->is_active ? 'Active' : 'Draft' }}</td></tr>
        @empty
            <tr><td colspan="7">No catalog products have been added.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="catalog-pagination">{{ $products->links() }}</div>
</section>
@endsection
