<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\PicRegistered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /** Salah PIN/password sebanyak ini → login dikunci selama LOCKOUT_SECONDS. */
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 300;

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'Username atau email wajib diisi.',
            'password.required' => 'PIN / password wajib diisi.',
        ]);

        $login = mb_strtolower(trim($validated['login']));
        $throttleKey = 'login:'.Str::transliterate($login).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'login' => sprintf('Terlalu banyak percobaan gagal. Coba lagi dalam %d menit.', max(1, $minutes)),
            ]);
        }

        $user = User::query()
            ->where(str_contains($login, '@') ? 'email' : 'username', $login)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            return back()
                ->withErrors(['login' => 'Username/email atau PIN/password tidak sesuai.'])
                ->onlyInput('login');
        }

        RateLimiter::clear($throttleKey);

        // Status baru dicek setelah kredensial benar → tidak membocorkan akun mana yang terdaftar.
        if (! $user->isActive()) {
            return back()
                ->withErrors(['login' => $user->status->blockedMessage($user->rejection_reason)])
                ->onlyInput('login');
        }

        // PIC login di PC bersama ruang tooling → tidak boleh "ingat saya".
        Auth::login($user, $user->isAdmin() && $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->must_change_password) {
            return to_route('account.pin.edit')
                ->with('success', sprintf('Halo %s, silakan ganti %s sementara Anda terlebih dahulu.', $user->name, $user->secretLabel()));
        }

        $homeUrl = $user->isAdmin() ? $user->homeUrl() : route('pic.dashboard');

        return redirect()
            ->intended($homeUrl)
            ->with('success', sprintf('Selamat datang, %s (%s).', $user->name, $user->isAdmin() ? 'Admin' : 'PIC Mesin'));
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Registrasi mandiri PIC → status pending, tidak langsung login.
     */
    public function register(Request $request): RedirectResponse
    {
        $request->merge(['username' => User::normalizeUsername($request->input('username'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => User::usernameRules(),
            'machine_name' => ['required', 'string', 'max:100'],
            'pin' => User::pinRules(),
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'machine_name.required' => 'Mesin / line wajib diisi.',
            ...User::credentialMessages(),
        ]);

        $pic = User::create([
            'name' => trim($validated['name']),
            'username' => $validated['username'],
            'machine_name' => trim($validated['machine_name']),
            'role' => User::ROLE_PIC,
            'status' => UserStatus::Pending,
            'password' => $validated['pin'],
        ]);

        // Notifikasi gagal tidak boleh menggagalkan registrasi.
        rescue(fn () => AdminAlert::toAdmins(new PicRegistered($pic)));

        return to_route('login')->with('success', sprintf(
            'Pendaftaran "%s" terkirim. Akun bisa dipakai setelah disetujui admin.',
            $validated['username'],
        ));
    }

    public function editPin(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Auth/ChangePin', [
            'isAdmin' => $user->isAdmin(),
            'forced' => $user->must_change_password,
        ]);
    }

    /**
     * Ganti PIN (PIC) / password (admin) oleh user sendiri. Wajib memasukkan yang lama.
     */
    public function updatePin(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $field = $user->isAdmin() ? 'password' : 'pin';

        $validated = $request->validate([
            'current' => ['required', 'string'],
            $field => [
                ...($user->isAdmin() ? User::adminPasswordRules() : User::pinRules()),
                'different:current',
            ],
        ], [
            'current.required' => sprintf('%s lama wajib diisi.', ucfirst($user->secretLabel())),
            "{$field}.different" => sprintf('%s baru harus berbeda dari yang lama.', ucfirst($user->secretLabel())),
            ...User::credentialMessages(),
        ]);

        if (! Hash::check($validated['current'], $user->password)) {
            throw ValidationException::withMessages([
                'current' => sprintf('%s lama tidak sesuai.', ucfirst($user->secretLabel())),
            ]);
        }

        $user->forceFill([
            'password' => $validated[$field],
            'must_change_password' => false,
        ])->save();

        $homeUrl = $user->isAdmin() ? $user->homeUrl() : route('pic.dashboard');

        return redirect($homeUrl)
            ->with('success', sprintf('%s berhasil diganti.', ucfirst($user->secretLabel())));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('success', 'Anda telah keluar dari sistem.');
    }
}