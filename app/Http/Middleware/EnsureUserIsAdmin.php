<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return redirect('/')
                ->with('error', 'Akses ditolak: halaman ini khusus Admin (Mas Sigit / Pak Apri).');
        }

        return $next($request);
    }
}