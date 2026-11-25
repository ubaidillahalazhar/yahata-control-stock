<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipasang setelah `auth`:
 * - akun yang dinonaktifkan/ditolak admin saat sedang login → sesinya langsung diputus;
 * - setelah admin mereset PIN/password → user wajib menggantinya sebelum membuka halaman lain.
 */
class EnsureAccountIsUsable
{
    /** Route yang tetap boleh dibuka saat wajib ganti PIN. */
    private const ALLOWED_WHILE_FORCED = ['account.pin.edit', 'account.pin.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->isActive()) {
            $message = $user->status->blockedMessage($user->rejection_reason);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->with('error', $message);
        }

        if ($user->must_change_password && ! $request->routeIs(...self::ALLOWED_WHILE_FORCED)) {
            return to_route('account.pin.edit');
        }

        return $next($request);
    }
}
