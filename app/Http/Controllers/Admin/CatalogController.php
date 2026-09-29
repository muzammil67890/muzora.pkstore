<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function products(): View
    {
        return view('admin.catalog.products', [
            'products' => Product::query()->with(['category', 'brand'])->latest()->paginate(20),
        ]);
    }

    public function categories(): View
    {
        return view('admin.catalog.categories', [
            'categories' => Category::query()->withCount('products')->orderBy('name')->paginate(20),
        ]);
    }

    public function brands(): View
    {
        return view('admin.catalog.brands', [
            'brands' => Brand::query()->withCount('products')->orderBy('name')->paginate(20),
        ]);
    }
}
