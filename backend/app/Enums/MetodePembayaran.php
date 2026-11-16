<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the school received a payment.
 */
enum MetodePembayaran: string
{
    case Dana = 'dana';
    case TransferBank = 'transfer_bank';
    case Tunai = 'tunai';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dana => 'DANA',
            self::TransferBank => 'Transfer Bank',
            self::Tunai => 'Tunai',
        };
    }
}
