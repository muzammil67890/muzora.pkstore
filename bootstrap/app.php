<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use App\Console\Commands\CreateAdmin;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\RequireCustomerWishlist;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([CreateAdmin::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) =>
            $request->is('admin', 'admin/*') ? route('admin.login') : route('login')
        );

        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
            'wishlist.auth' => RequireCustomerWishlist::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
