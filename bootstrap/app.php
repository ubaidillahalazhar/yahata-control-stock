<?php

use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'account.usable' => EnsureAccountIsUsable::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        // User yang sudah login membuka /login atau /register → halaman awal sesuai role.
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()?->homeUrl() ?? '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Halaman yang lama terbuka (mis. form QR di HP) → token CSRF kedaluwarsa.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() === 419) {
                return back()->with('error', 'Sesi halaman kedaluwarsa. Silakan kirim ulang formulir.');
            }

            return $response;
        });
    })->create();