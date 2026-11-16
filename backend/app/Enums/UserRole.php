<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two roles the system recognises.
 *
 * Santri never log in; they are only ever managed through their wali or by
 * Admin / Tata Usaha.
 */
enum UserRole: string
{
    case Wali = 'wali';
    case Admin = 'admin';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Wali => 'Wali Santri',
            self::Admin => 'Admin / Tata Usaha',
        };
    }
}
