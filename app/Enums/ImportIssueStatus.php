<?php

namespace App\Enums;

enum ImportIssueStatus: string
{
    /** Tool belum ada di tabel utama → tidak dimasukkan sampai datanya lengkap. */
    case NewHeld = 'NEW_HELD';

    /** Tool sudah ada → tabel utama tetap memakai data lama, update dari Excel ditahan. */
    case UpdateHeld = 'UPDATE_HELD';

    /** Baris duplikat tidak lengkap; baris lain dengan Tools Name YES sama yang lengkap sudah dipakai. */
    case Duplicate = 'DUPLICATE';

    public function label(): string
    {
        return match ($this) {
            self::NewHeld => 'Tool baru — belum masuk dashboard',
            self::UpdateHeld => 'Tool lama — update ditahan',
            self::Duplicate => 'Baris duplikat — diabaikan',
        };
    }
}
