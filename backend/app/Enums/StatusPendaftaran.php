<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Progress of a single registration submission for one academic year.
 */
enum StatusPendaftaran: string
{
    case Diajukan = 'diajukan';
    case Diverifikasi = 'diverifikasi';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Diverifikasi => 'Sedang Diverifikasi',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * Whether the submission has been decided.
     */
    public function isDecided(): bool
    {
        return in_array($this, [self::Diterima, self::Ditolak], true);
    }

    /**
     * Whether the registration may move to `$next`.
     *
     * The state machine, owned by the enum so the admin decision endpoint and
     * the tests share one map. Undecided submissions may be accepted or
     * rejected; a submitted decision is final — a rejection is not reopened, and
     * an acceptance is not unmade, because `diterima` has already opened the
     * payment lane and `ditolak` has already ended the row.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Diajukan, self::Diverifikasi => in_array($next, [self::Diterima, self::Ditolak], true),
            self::Diterima, self::Ditolak => false,
        };
    }
}
