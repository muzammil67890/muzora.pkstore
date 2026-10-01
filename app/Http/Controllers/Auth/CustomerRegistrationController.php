<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerRegistrationController extends Controller
{
    public function __construct(private readonly CartService $carts)
    {
    }

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        $guestSessionId = $request->session()->getId();
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));

        Auth::guard('web')->login($user);
        $merge = $this->carts->mergeGuestCart($user, $guestSessionId);
        $request->session()->regenerate();
        $response = redirect()->intended(route('account.index'));

        if ($merge['skipped'] > 0 || $merge['adjusted'] > 0) {
            $notices = [];
            if ($merge['skipped'] > 0) {
                $notices[] = $merge['skipped'].' unavailable item(s) were removed.';
            }
            if ($merge['adjusted'] > 0) {
                $notices[] = $merge['adjusted'].' item quantity(ies) were adjusted to current stock.';
            }
            $response->with('status', 'Your guest cart was merged. '.implode(' ', $notices));
        }

        return $response;
    }
}
