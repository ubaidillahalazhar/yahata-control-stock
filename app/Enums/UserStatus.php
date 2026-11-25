<?php

namespace App\Enums;

enum UserStatus: string
{
    /** Registrasi mandiri PIC → belum bisa login sampai disetujui admin. */
    case Pending = 'pending';

    case Active = 'active';

    /** Registrasi ditolak admin (alasan di users.rejection_reason). */
    case Rejected = 'rejected';

    /** Akun pernah aktif lalu dinonaktifkan admin. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Active => 'Aktif',
            self::Rejected => 'Ditolak',
            self::Disabled => 'Nonaktif',
        };
    }

    /** Pesan untuk user yang mencoba login / sesinya diputus. */
    public function blockedMessage(?string $reason = null): string
    {
        return match ($this) {
            self::Pending => 'Akun Anda masih menunggu persetujuan admin. Silakan hubungi admin tooling.',
            self::Rejected => 'Pendaftaran akun Anda ditolak admin'.($reason ? ': '.$reason : '.'),
            self::Disabled => 'Akun Anda dinonaktifkan admin. Silakan hubungi admin tooling.',
            self::Active => '',
        };
    }
}
