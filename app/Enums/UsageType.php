<?php

namespace App\Enums;

enum UsageType: string
{
    case Usage = 'USAGE';
    case Reset = 'RESET';

    /**
     * Selisih counter mesin vs sistem untuk siklus tool yang SUDAH ditutup (laporan kerusakan).
     * Masuk Actual bulanan, tetapi tidak menambah life tool yang sedang berjalan.
     */
    case Adjust = 'ADJUST';

    public function label(): string
    {
        return match ($this) {
            self::Usage => 'Pemakaian',
            self::Reset => 'Ganti Tool (Reset Life)',
            self::Adjust => 'Koreksi Count (Siklus Lama)',
        };
    }
}
