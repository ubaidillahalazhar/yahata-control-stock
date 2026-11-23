<?php

namespace App\Enums;

enum DamageReason: string
{
    case Chipping = 'CHIPPING';
    case WearNormal = 'WEAR_NORMAL';
    case Broken = 'BROKEN';
    case SetupError = 'SETUP_ERROR';

    public function label(): string
    {
        return match ($this) {
            self::Chipping => 'Chipping',
            self::WearNormal => 'Wear / Aus Normal',
            self::Broken => 'Broken / Patah',
            self::SetupError => 'Setup Error',
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
