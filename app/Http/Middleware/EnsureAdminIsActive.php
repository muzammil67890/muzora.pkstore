<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->regenerate();

            return redirect()->route('admin.login')->withErrors([
                'email' => 'Your administrator access is unavailable. Please sign in again or contact the store owner.',
            ]);
        }

        return $next($request);
    }
}
