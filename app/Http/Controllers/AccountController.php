<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'pic',
                'machine_name' => $user->machine_name,
                'created_at' => $user->created_at?->format('d/m/Y H:i') ?? '—',
            ]);

        return Inertia::render('Accounts/Index', [
            'users' => $users,
            'currentUserId' => $request->user()?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['admin', 'pic'])],
            'machine_name' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar pada akun lain.',
            'role.required' => 'Hak akses (Role) wajib dipilih.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
            'machine_name' => $validated['machine_name'] ?: ($validated['role'] === 'admin' ? 'All Lines / Admin' : null),
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', sprintf('Akun %s "%s" berhasil ditambahkan.', strtoupper($user->role), $user->name));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user() && $request->user()->id === $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan saat ini.');
        }

        if (User::query()->count() <= 1) {
            return back()->with('error', 'Penghapusan ditolak: minimal harus tersisa 1 akun di dalam sistem.');
        }

        $deletedName = $user->name;
        $user->delete();

        return back()->with('success', sprintf('Akun "%s" berhasil dihapus dari sistem.', $deletedName));
    }
}