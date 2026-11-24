<?php

namespace App\Enums;

enum DamageAction: string
{
    case ReplaceNew = 'REPLACE_NEW';
    case RegrindRepair = 'REGRIND_REPAIR';

    public function label(): string
    {
        return match ($this) {
            self::ReplaceNew => 'Ganti Baru (Replace New)',
            self::RegrindRepair => 'Regrind / Repair',
        };
    }

    public function cutsStock(): bool
    {
        return $this === self::ReplaceNew;
    }

    /**
     * @return list<array{value: string, label: string, cuts_stock: bool}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'cuts_stock' => $case->cutsStock(),
            ],
            self::cases(),
        );
    }
}
