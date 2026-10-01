@extends('layouts.store')

@section('title', 'Categories | MUZORA.PK Admin')

@section('content')
<section class="account-page">
    <div class="section-heading"><div><span class="section-kicker">Catalog</span><h1>Categories</h1><p>Read-only catalog access for the authentication foundation phase.</p></div></div>
    <nav class="account-nav" aria-label="Admin catalog navigation"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.products.index') }}">Products</a><a href="{{ route('admin.brands.index') }}">Brands</a></nav>
    <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Slug</th><th>Parent</th><th>Products</th><th>Status</th></tr></thead><tbody>
        @forelse ($categories as $category)
            <tr><td>{{ $category->name }}</td><td>{{ $category->slug }}</td><td>{{ $category->parent?->name ?: '—' }}</td><td>{{ $category->products_count }}</td><td>{{ $category->is_active ? 'Active' : 'Inactive' }}</td></tr>
        @empty
            <tr><td colspan="5">No categories have been added.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="catalog-pagination">{{ $categories->links() }}</div>
</section>
@endsection
