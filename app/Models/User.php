<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Admin: login email + password (nanti juga SSO Superapps via `nik`).
 * PIC: registrasi mandiri → disetujui admin → login username + PIN 6 digit.
 * Kolom `password` menyimpan hash password (admin) atau PIN (PIC).
 *
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $nik
 * @property string|null $email
 * @property string $role
 * @property UserStatus $status
 * @property string|null $machine_name
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property bool $must_change_password
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PIC = 'pic';

    protected $fillable = [
        'name',
        'username',
        'nik',
        'email',
        'role',
        'status',
        'machine_name',
        'password',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** Halaman awal setelah login: admin → dashboard, PIC → portal menu 2x2 PIC. */
    public function homeUrl(): string
    {
        return $this->isAdmin() ? route('dashboard') : route('pic.dashboard');
    }

    /** Sebutan kredensial di UI & pesan: PIC memakai PIN, admin memakai password. */
    public function secretLabel(): string
    {
        return $this->isAdmin() ? 'password' : 'PIN';
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    /**
     * Username: huruf kecil/angka/titik/garis bawah, 3–30 karakter, wajib ada huruf
     * (tidak boleh angka semua supaya tidak pernah terlihat seperti NIK).
     *
     * @return array<int, mixed>
     */
    public static function usernameRules(?int $ignoreId = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:30',
            'regex:/^[a-z0-9._]+$/', 'regex:/[a-z]/',
            Rule::unique('users', 'username')->ignore($ignoreId),
        ];
    }

    /** @return array<int, string> */
    public static function pinRules(): array
    {
        return ['required', 'string', 'digits:6', 'confirmed'];
    }

    /** @return array<int, string> */
    public static function adminPasswordRules(): array
    {
        return ['required', 'string', 'min:8', 'confirmed'];
    }

    /** @return array<string, string> */
    public static function credentialMessages(): array
    {
        return [
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.max' => 'Username maksimal 30 karakter.',
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, dan garis bawah, serta wajib mengandung minimal satu huruf.',
            'username.unique' => 'Username ini sudah dipakai akun lain.',
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits' => 'PIN harus 6 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak cocok.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    public static function normalizeUsername(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
