<?php

namespace App\Enums\Sppd;

enum JenisPencairan: string
{
    case UangMuka = 'uang_muka';

    public function label(): string
    {
        return match ($this) {
            self::UangMuka => 'Uang Muka',
        };
    }
}
