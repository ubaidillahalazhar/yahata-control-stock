<?php

namespace App\Enums;

/** Asal sebuah baris tool_usage_logs. */
enum UsageSource: string
{
    /** Form Pemakaian / tombol Ganti Tool di aplikasi. */
    case Manual = 'manual';

    /** Import template lama (Tools Name YES per baris) — hanya data lama. */
    case Excel = 'excel';

    /** Import export "Machine Result" per periode (bulan). */
    case MachineResult = 'machine_result';

    /** Dibuat otomatis oleh laporan kerusakan (koreksi count + reset life). */
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Input aplikasi',
            self::Excel => 'Import Excel',
            self::MachineResult => 'Import Machine Result',
            self::Damage => 'Laporan kerusakan',
        };
    }
}
