<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('account.index');
    }

    public function orders(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        return view('account.orders.index', [
            'orders' => $request->user('web')->orders()->with('paymentMethod')->latest('created_at')->paginate(10),
        ]);
    }

    public function order(Request $request, Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['items', 'paymentMethod', 'paymentProofs.reviewer']);

        return view('account.orders.show', ['order' => $order]);
    }

    public function editProfile(): View
    {
        return view('account.profile', ['user' => request()->user('web')]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user('web');
        $data = $request->safe()->only(['name', 'email', 'phone', 'address']);

        $emailChanged = $user->email !== $data['email'];
        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Your profile has been updated.');
    }

    public function editPassword(): View
    {
        return view('account.password');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user('web')->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('status', 'Your password has been updated.');
    }
}
