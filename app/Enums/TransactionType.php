<?php

namespace App\Enums;

enum TransactionType: string
{
    case In = 'IN';
    case Out = 'OUT';
    case OpnameAdjust = 'OPNAME_ADJUST';
    case DamageReplacement = 'DAMAGE_REPLACEMENT';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Barang Masuk (IN)',
            self::Out => 'Pengambilan (OUT)',
            self::OpnameAdjust => 'Penyesuaian Opname',
            self::DamageReplacement => 'Penggantian Rusak',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
