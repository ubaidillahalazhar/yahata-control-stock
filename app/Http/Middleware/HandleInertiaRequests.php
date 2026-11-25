<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Root template Inertia: resources/views/app.blade.php
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props yang tersedia di semua halaman React (usePage().props).
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'company' => config('app.company'),
                'department' => config('app.department'),
                'url' => rtrim((string) config('app.url'), '/'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role ?? User::ROLE_PIC,
                    'machine_name' => $user->machine_name,
                    'is_admin' => $user->isAdmin(),
                ] : null,
                // Badge "menunggu persetujuan" di menu admin.
                'pending_accounts' => fn (): int => $user?->isAdmin()
                    ? User::query()->where('status', UserStatus::Pending)->count()
                    : 0,
            ],
            'flash' => fn (): array => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'import_result' => $request->session()->get('import_result'),
                'opname_preview' => $request->session()->get('opname_preview'),
                'opname_result' => $request->session()->get('opname_result'),
                'usage_preview' => $request->session()->get('usage_preview'),
                'usage_result' => $request->session()->get('usage_result'),
            ],
        ];
    }
}
