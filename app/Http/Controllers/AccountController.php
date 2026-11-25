<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('approver:id,name')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN role = 'admin' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'nik' => $user->nik,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status->value,
                'status_label' => $user->status->label(),
                'machine_name' => $user->machine_name,
                'rejection_reason' => $user->rejection_reason,
                'must_change_password' => $user->must_change_password,
                'approved_by' => $user->approver?->name,
                'approved_at' => $user->approved_at?->format('d/m/Y H:i'),
                'created_at' => $user->created_at?->format('d/m/Y H:i') ?? '—',
            ]);

        return Inertia::render('Accounts/Index', [
            'users' => $users,
            'currentUserId' => $request->user()?->id,
        ]);
    }

    /**
     * Akun yang dibuat admin langsung aktif.
     * Admin: email + password (+ NIK untuk SSO Superapps). PIC: username + PIN 6 digit.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalize($request);
        $isAdmin = $request->input('role') === User::ROLE_ADMIN;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PIC])],
            'machine_name' => [$isAdmin ? 'nullable' : 'required', 'string', 'max:100'],
            ...$this->identityRules($isAdmin),
            ...($isAdmin ? ['password' => User::adminPasswordRules()] : ['pin' => User::pinRules()]),
        ], $this->messages());

        $user = User::create([
            'name' => trim($validated['name']),
            'username' => $validated['username'] ?? null,
            'nik' => $validated['nik'] ?? null,
            'email' => $validated['email'] ?? null,
            'role' => $validated['role'],
            'status' => UserStatus::Active,
            'machine_name' => filled($validated['machine_name'] ?? null)
                ? trim($validated['machine_name'])
                : ($isAdmin ? 'All Lines / Admin' : null),
            'password' => $isAdmin ? $validated['password'] : $validated['pin'],
        ]);

        $user->forceFill(['approved_by' => $request->user()->id, 'approved_at' => now()])->save();

        return back()->with('success', sprintf('Akun %s "%s" berhasil ditambahkan.', $isAdmin ? 'Admin' : 'PIC', $user->name));
    }

    /**
     * Edit data akun (bukan PIN/password — itu lewat reset). Role tidak bisa diubah.
     * Username hanya bisa diubah di sini (oleh admin), tidak oleh user sendiri.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->normalize($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'machine_name' => [$user->isAdmin() ? 'nullable' : 'required', 'string', 'max:100'],
            ...$this->identityRules($user->isAdmin(), $user->id),
        ], $this->messages());

        $user->fill([
            'name' => trim($validated['name']),
            'username' => $validated['username'] ?? null,
            'nik' => $validated['nik'] ?? null,
            'email' => $validated['email'] ?? null,
            'machine_name' => filled($validated['machine_name'] ?? null) ? trim($validated['machine_name']) : null,
        ])->save();

        return back()->with('success', sprintf('Data akun "%s" diperbarui.', $user->name));
    }

    /**
     * Setujui / tolak registrasi, nonaktifkan, atau aktifkan kembali akun.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Rejected->value, UserStatus::Disabled->value])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $status = UserStatus::from($validated['status']);

        if ($request->user()->is($user)) {
            return back()->with('error', 'Anda tidak dapat mengubah status akun Anda sendiri.');
        }

        if ($status !== UserStatus::Active && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Ditolak: minimal harus ada 1 akun Admin yang aktif.');
        }

        $wasPending = $user->status === UserStatus::Pending;

        $user->forceFill([
            'status' => $status,
            'rejection_reason' => $status === UserStatus::Rejected ? (trim((string) ($validated['reason'] ?? '')) ?: null) : null,
            ...($status === UserStatus::Active && ! $user->approved_at
                ? ['approved_by' => $request->user()->id, 'approved_at' => now()]
                : []),
        ])->save();

        $message = match ($status) {
            UserStatus::Active => $wasPending
                ? sprintf('Registrasi "%s" disetujui. Akun sudah bisa dipakai login.', $user->name)
                : sprintf('Akun "%s" diaktifkan kembali.', $user->name),
            UserStatus::Rejected => sprintf('Registrasi "%s" ditolak.', $user->name),
            default => sprintf('Akun "%s" dinonaktifkan. Sesi login-nya otomatis diputus.', $user->name),
        };

        return back()->with('success', $message);
    }

    /**
     * Lupa PIN/password → admin membuat yang sementara; user wajib menggantinya saat login berikutnya.
     */
    public function resetSecret(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'Untuk akun Anda sendiri, gunakan menu Ganti Password.');
        }

        $temporary = $user->isAdmin()
            ? Str::password(10, symbols: false)
            : str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $user->forceFill(['password' => $temporary, 'must_change_password' => true])->save();

        return back()->with('success', sprintf(
            '%s sementara untuk "%s": %s — berikan langsung ke yang bersangkutan. Wajib diganti saat login berikutnya.',
            ucfirst($user->secretLabel()),
            $user->name,
            $temporary,
        ));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan saat ini.');
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Penghapusan ditolak: minimal harus ada 1 akun Admin yang aktif.');
        }

        $deletedName = $user->name;
        $user->delete();

        return back()->with('success', sprintf('Akun "%s" berhasil dihapus dari sistem.', $deletedName));
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->isAdmin()
            && $user->isActive()
            && User::query()->where('role', User::ROLE_ADMIN)->where('status', UserStatus::Active)->count() <= 1;
    }

    private function normalize(Request $request): void
    {
        $request->merge([
            'username' => User::normalizeUsername($request->input('username')),
            'email' => filled($request->input('email')) ? mb_strtolower(trim((string) $request->input('email'))) : null,
            'nik' => filled($request->input('nik')) ? trim((string) $request->input('nik')) : null,
        ]);
    }

    /**
     * Admin wajib email (login cadangan bila Superapps mati); NIK opsional, unik.
     * PIC wajib username; NIK opsional (diisi bila nanti PIC ikut Superapps).
     *
     * @return array<string, array<int, mixed>>
     */
    private function identityRules(bool $isAdmin, ?int $ignoreId = null): array
    {
        $username = User::usernameRules($ignoreId);

        if ($isAdmin) {
            $username[0] = 'nullable';
        }

        return [
            'username' => $username,
            'email' => [$isAdmin ? 'required' : 'nullable', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($ignoreId)],
            'nik' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-]+$/', Rule::unique('users', 'nik')->ignore($ignoreId)],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'role.required' => 'Hak akses (role) wajib dipilih.',
            'machine_name.required' => 'Mesin / line wajib diisi untuk akun PIC.',
            'email.required' => 'Email wajib diisi untuk akun Admin.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar pada akun lain.',
            'nik.unique' => 'NIK ini sudah terdaftar pada akun lain.',
            'nik.regex' => 'NIK hanya boleh berisi angka/huruf.',
            ...User::credentialMessages(),
        ];
    }
}
