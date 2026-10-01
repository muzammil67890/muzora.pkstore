<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateReviewRequest;
use App\Models\Admin;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'moderateAny', Review::class);
        $filters = Validator::make($request->query(), [
            'status' => ['nullable', Rule::in(array_merge(Review::STATUSES, ['all']))],
        ])->validate();

        $query = Review::query()->with(['user', 'product', 'order'])->latest();
        $status = $filters['status'] ?? 'pending';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('admin.reviews.index', [
            'reviews' => $query->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function moderate(ModerateReviewRequest $request, Review $review): RedirectResponse
    {
        $admin = $request->user('admin');
        $this->authorizeForUser($admin, 'moderate', $review);
        $data = $request->safe()->only(['status', 'moderation_note']);
        $this->reviews->moderate($review, $admin, $data['status'], $data['moderation_note'] ?? null);

        return back()->with('status', 'Review moderation status saved.');
    }
}
