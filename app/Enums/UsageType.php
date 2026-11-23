<?php

namespace App\Enums;

enum UsageType: string
{
    case Usage = 'USAGE';
    case Reset = 'RESET';

    public function label(): string
    {
        return match ($this) {
            self::Usage => 'Pemakaian',
            self::Reset => 'Ganti Tool (Reset Life)',
        };
    }
}
