<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPaymentRequest;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Services\Payments\PaymentProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReviewController extends Controller
{
    public function __construct(private readonly PaymentProofService $proofs)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeForUser($request->user('admin'), 'adminViewAny', Order::class);

        return view('admin.payments.index', [
            'orders' => Order::query()
                ->with(['user', 'paymentMethod', 'paymentProofs' => fn ($query) => $query->where('status', 'submitted')])
                ->where('payment_status', 'submitted')
                ->latest('updated_at')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeForUser($request->user('admin'), 'adminView', $order);
        $order->load(['items', 'user', 'paymentMethod', 'paymentProofs.reviewer', 'paymentProofs.user']);

        return view('admin.orders.show', [
            'order' => $order,
            'nextStatuses' => $order->allowedNextStatuses(),
        ]);
    }

    public function review(ReviewPaymentRequest $request, Order $order): RedirectResponse
    {
        $admin = $request->user('admin');
        $this->authorizeForUser($admin, 'adminUpdate', $order);
        $data = $request->safe()->only(['decision', 'rejection_reason']);

        $this->proofs->review(
            $order,
            $admin,
            $data['decision'],
            $data['rejection_reason'] ?? null
        );

        return redirect()->route('admin.orders.show', $order)
            ->with('status', $data['decision'] === 'verified' ? 'Payment marked as verified.' : 'Payment rejected; the customer may submit corrected proof.');
    }

    public function download(Request $request, Order $order, PaymentProof $proof): StreamedResponse
    {
        $this->authorizeForUser($request->user('admin'), 'adminView', $order);
        $this->authorizeForUser($request->user('admin'), 'adminView', $proof);
        abort_unless((int) $proof->order_id === (int) $order->getKey(), 404);

        $prefix = 'orders/'.$order->getKey().'/';
        abort_unless(
            str_starts_with($proof->screenshot_path, $prefix)
            && ! str_contains($proof->screenshot_path, '..')
            && Storage::disk('payment-proofs')->exists($proof->screenshot_path),
            404
        );

        $extension = pathinfo($proof->screenshot_path, PATHINFO_EXTENSION);
        abort_unless(in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp'], true), 404);

        return Storage::disk('payment-proofs')->download(
            $proof->screenshot_path,
            'payment-proof-'.$proof->getKey().'.'.$extension,
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
