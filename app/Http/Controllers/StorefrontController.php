<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Product;
use App\Models\Review;
use App\Services\Cart\CartService;
use App\Services\Reviews\ReviewService;
use App\Services\Catalog\CatalogQueryService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
        private readonly CartService $carts,
        private readonly ReviewService $reviews
    ) {
    }

    public function home(Request $request): View
    {
        return view('storefront', [
            'page' => 'home',
            'cartCount' => $this->carts->countCurrent($request->user('web'), $request->session()->getId()),
            'products' => $this->catalog->featuredProducts()->map(fn (Product $product) => $this->productCard($product))->all(),
            'categories' => $this->catalog->homepageCategories()->map(fn ($category) => [
                $category->name,
                $category->products_count.' products',
                $category->icon,
                $category->slug,
            ])->all(),
        ]);
    }

    public function shop(CatalogFilterRequest $request): View
    {
        $filters = $request->validated();
        $filters['in_stock'] = $request->input('in_stock', '1');
        $products = $this->catalog->paginate($filters);
        $products->through(fn (Product $product) => $this->productCard($product));

        return view('storefront', [
            'page' => 'shop',
            'cartCount' => $this->carts->countCurrent($request->user('web'), $request->session()->getId()),
            'products' => $products,
            'categories' => $this->catalog->homepageCategories(),
            'activeFilters' => $filters,
        ]);
    }

    public function show(Request $request, Product $product): View
    {
        abort_unless($product->is_active, 404);
        $product->load(['brand', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('position')]);
        $product->loadAvg('approvedReviews', 'rating')->loadCount('approvedReviews');

        $detail = $this->productCard($product);
        $detail['description'] = $product->description ?: $product->short_description;
        $detail['stock'] = (int) $product->stock_quantity;
        $detail['category_name'] = $product->category?->name;

        $user = $request->user('web');
        $approvedReviews = $product->approvedReviews()->with('user')->latest()->paginate(10)->withQueryString();
        $eligibleOrders = $user ? $this->reviews->eligibleOrders($user, $product) : collect();
        $myReviews = $user
            ? Review::query()->with('order')->where('user_id', $user->getKey())->where('product_id', $product->getKey())->latest()->get()
            : collect();

        return view('storefront', [
            'page' => 'product',
            'cartCount' => $this->carts->countCurrent($user, $request->session()->getId()),
            'detail' => $detail,
            'approvedReviews' => $approvedReviews,
            'eligibleReviewOrders' => $eligibleOrders,
            'myProductReviews' => $myReviews,
        ]);
    }

    /** @return array<string, mixed> */
    private function productCard(Product $product): array
    {
        $compareAt = $product->compare_at_price !== null ? (float) $product->compare_at_price : null;
        $price = (float) $product->price;
        $onSale = $compareAt !== null && $compareAt > $price;
        $imagePath = $product->images->first()?->path;
        $brandName = $product->brand?->name ?? 'MUZORA';

        return [
            'id' => $product->slug,
            'title' => $product->name,
            'brand' => mb_strtoupper($brandName),
            'category' => $product->category?->slug ?? 'uncategorized',
            'price' => $price,
            'price_formatted' => Money::formatDisplayMinor(Money::toMinor((string) $product->price)),
            'old' => $onSale ? $compareAt : null,
            'old_formatted' => $onSale ? Money::formatDisplayMinor(Money::toMinor((string) $product->compare_at_price)) : null,
            'rating' => $product->approved_reviews_avg_rating !== null ? number_format((float) $product->approved_reviews_avg_rating, 1) : '—',
            'reviews' => (int) ($product->approved_reviews_count ?? 0),
            'icon' => $product->icon ?: 'devices_other',
            'badge' => $onSale ? (int) round((1 - $price / $compareAt) * 100).'% OFF' : ($product->is_featured ? 'FEATURED' : ''),
            'in_stock' => $product->stock_quantity > 0,
            'image' => $imagePath ? Storage::disk('public')->url($imagePath) : null,
            'image_alt' => $product->images->first()?->alt_text ?: $product->name,
        ];
    }
}
