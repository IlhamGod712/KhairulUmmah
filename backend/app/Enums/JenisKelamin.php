<?php

declare(strict_types=1);

namespace App\Enums;

enum JenisKelamin: string
{
    case LakiLaki = 'laki_laki';
    case Perempuan = 'perempuan';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::LakiLaki => 'Laki-laki',
            self::Perempuan => 'Perempuan',
        };
    }
}
