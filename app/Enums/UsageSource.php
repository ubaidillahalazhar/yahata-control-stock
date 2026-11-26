<?php

namespace App\Enums;

/** Asal sebuah baris tool_usage_logs. */
enum UsageSource: string
{
    /** Form Pemakaian / tombol Ganti Tool di aplikasi. */
    case Manual = 'manual';

    case Excel = 'excel';

    /** Dibuat otomatis oleh laporan kerusakan (koreksi count + reset life). */
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Input aplikasi',
            self::Excel => 'Import Excel',
            self::Damage => 'Laporan kerusakan',
        };
    }
}
