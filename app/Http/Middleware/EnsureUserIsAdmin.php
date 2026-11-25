<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            $redirect = redirect($user?->homeUrl() ?? route('login'));

            // Membuka "/" (mis. klik logo) bukan pelanggaran → arahkan tanpa pesan error.
            return $request->routeIs('dashboard')
                ? $redirect
                : $redirect->with('error', 'Akses ditolak: halaman ini khusus Admin.');
        }

        return $next($request);
    }
}
