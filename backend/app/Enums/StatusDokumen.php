<?php

namespace App\Enums;

/**
 * Result of an admin reviewing an uploaded document.
 */
enum StatusDokumen: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Verifikasi',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * Whether the document has cleared review.
     */
    public function isAccepted(): bool
    {
        return $this === self::Diterima;
    }

    /**
     * Whether a review outcome may move a document from this state.
     *
     * `Menunggu` may be decided either way. `Ditolak` may only be re-decided by
     * a fresh upload, which replaces the row rather than moving it, so it is
     * terminal here — leaving it out of the map would silently allow
     * `ditolak -> diterima`, i.e. an admin re-opening a rejection without the
     * guardian having supplied the corrected file.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Menunggu => in_array($next, [self::Diterima, self::Ditolak], true),
            self::Diterima, self::Ditolak => false,
        };
    }
}
