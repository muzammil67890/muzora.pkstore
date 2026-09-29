<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitPaymentProofRequest;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Services\Payments\PaymentProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function __construct(private readonly PaymentProofService $proofs)
    {
    }

    public function store(SubmitPaymentProofRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $data = $request->safe()->only(['transaction_id', 'notes']);
        $this->proofs->submit(
            $order,
            $request->user('web'),
            $data,
            $request->file('screenshot')
        );

        return redirect()->route('account.orders.show', $order)
            ->with('status', 'Your payment proof has been submitted for review.');
    }

    public function download(Order $order, PaymentProof $proof): StreamedResponse
    {
        $this->authorize('view', $order);
        $this->authorize('view', $proof);
        abort_unless(
            (int) $proof->order_id === (int) $order->getKey()
            && (int) $proof->user_id === (int) request()->user('web')->getKey(),
            404
        );

        return $this->downloadPrivateProof($order, $proof);
    }

    private function downloadPrivateProof(Order $order, PaymentProof $proof): StreamedResponse
    {
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
