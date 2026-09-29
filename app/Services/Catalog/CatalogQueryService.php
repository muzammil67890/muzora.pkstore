<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CatalogQueryService
{
    public function homepageCategories()
    {
        return Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn (Builder $query) => $query->where('is_active', true)])
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    public function featuredProducts(int $limit = 4)
    {
        return Product::query()
            ->active()
            ->with(['brand', 'category', 'images' => fn (Builder $query) => $query->orderByDesc('is_primary')->orderBy('position')])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->orderByDesc('is_featured')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with(['brand', 'category', 'images' => fn (Builder $images) => $images->orderByDesc('is_primary')->orderBy('position')])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews');

        $search = trim((string) ($filters['q'] ?? ''));
        $categorySlug = trim((string) ($filters['category'] ?? ''));

        // Existing category links used ?q=audio; continue to accept those during the UI transition.
        if ($categorySlug === '' && $search !== '') {
            $legacyCategory = Category::query()
                ->where('is_active', true)
                ->where('slug', $search)
                ->first();

            if ($legacyCategory) {
                $categorySlug = $legacyCategory->slug;
                $search = '';
            }
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($categorySlug !== '') {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $categorySlug)->where('is_active', true));
        }

        $brandSlug = trim((string) ($filters['brand'] ?? ''));
        if ($brandSlug !== '') {
            $query->whereHas('brand', fn (Builder $brand) => $brand->where('slug', $brandSlug)->where('is_active', true));
        }

        if (filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('stock_quantity', '>', 0);
        }

        if (filter_var($filters['on_sale'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price', '<=', $filters['max_price']);
        }

        match ($filters['sort'] ?? 'featured') {
            'price-asc' => $query->orderBy('price')->orderBy('id'),
            'price-desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'newest' => $query->latest('id'),
            default => $query->orderByDesc('is_featured')->latest('id'),
        };

        return $query->paginate(12)->withQueryString();
    }
}
