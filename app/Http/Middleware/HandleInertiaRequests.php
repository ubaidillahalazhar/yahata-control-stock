<?php

namespace App\Http\Middleware;

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
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'company' => config('app.company'),
                'department' => config('app.department'),
                'url' => rtrim((string) config('app.url'), '/'),
            ],
            'flash' => fn (): array => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'import_result' => $request->session()->get('import_result'),
                'opname_preview' => $request->session()->get('opname_preview'),
                'opname_result' => $request->session()->get('opname_result'),
            ],
        ];
    }
}
