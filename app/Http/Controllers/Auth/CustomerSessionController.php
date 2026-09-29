<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerLoginRequest;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerSessionController extends Controller
{
    public function __construct(private readonly CartService $carts)
    {
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(CustomerLoginRequest $request): RedirectResponse
    {
        $guestSessionId = $request->session()->getId();
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::guard('web')->user();
        $merge = $user ? $this->carts->mergeGuestCart($user, $guestSessionId) : ['skipped' => 0, 'adjusted' => 0];
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

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }
}
