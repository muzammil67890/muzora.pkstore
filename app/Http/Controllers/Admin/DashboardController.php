<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'productCount' => Product::query()->count(),
            'categoryCount' => Category::query()->count(),
            'brandCount' => Brand::query()->count(),
            'customerCount' => User::query()->count(),
            'orderCount' => Order::query()->count(),
        ]);
    }
}
