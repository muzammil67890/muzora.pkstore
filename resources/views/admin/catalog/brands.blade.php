@extends('layouts.store')

@section('title', 'Brands | MUZORA.PK Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Catalog</span><h1>Brands</h1><p>Read-only catalog access for the authentication foundation phase.</p></div></div>
    <nav class="account-nav" aria-label="Admin catalog navigation"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.products.index') }}">Products</a><a href="{{ route('admin.categories.index') }}">Categories</a></nav>
    <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Status</th></tr></thead><tbody>
        @forelse ($brands as $brand)
            <tr><td>{{ $brand->name }}</td><td>{{ $brand->slug }}</td><td>{{ $brand->products_count }}</td><td>{{ $brand->is_active ? 'Active' : 'Inactive' }}</td></tr>
        @empty
            <tr><td colspan="4">No brands have been added.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="catalog-pagination">{{ $brands->links() }}</div>
</section>
@endsection
