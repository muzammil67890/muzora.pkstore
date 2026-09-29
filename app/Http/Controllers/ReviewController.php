<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Product;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function store(StoreReviewRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('create', [Review::class, $product]);
        $this->reviews->submit($request->user('web'), $product, $request->safe()->only([
            'order_id', 'rating', 'title', 'body',
        ]));

        return redirect()->route('product.show', $product)
            ->with('status', 'Your review was submitted and is awaiting moderation.');
    }
}
