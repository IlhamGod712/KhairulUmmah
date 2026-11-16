<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a student record.
 *
 * A registration becomes `Aktif` only after the registration payment is
 * recorded; see the payment flow in Phase 6.
 */
enum SantriStatus: string
{
    case Pendaftaran = 'pendaftaran';
    case Verifikasi = 'verifikasi';
    case MenungguPembayaran = 'menunggu_pembayaran';
    case Aktif = 'aktif';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendaftaran => 'Pendaftaran',
            self::Verifikasi => 'Verifikasi Berkas',
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::Aktif => 'Aktif',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Whether a student in this status is counted as an active student.
     */
    public function isActive(): bool
    {
        return $this === self::Aktif;
    }

    /**
     * Whether the status is a terminal state, i.e. no further transition.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Aktif, self::Ditolak, self::Dibatalkan], true);
    }

    /**
     * Whether the student may move to `$next`.
     *
     * The state machine, owned by the enum so every endpoint and test shares
     * one map:
     *
     *   pendaftaran         -> verifikasi | menunggu_pembayaran | ditolak
     *   verifikasi          -> menunggu_pembayaran | ditolak
     *   menunggu_pembayaran -> aktif | ditolak
     *   aktif, ditolak, dibatalkan -> (terminal)
     *
     * The two moves guarded here are the ones with money behind them.
     * `menunggu_pembayaran -> aktif` may render a student active, and this enum
     * documents that it is legal; *when* it is legal — only once the blocking fee
     * is paid — is decided by the activation path, not by this map. `aktif` is
     * terminal: a paid fee is not un-paid, so an activated student stays active.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pendaftaran => in_array($next, [self::Verifikasi, self::MenungguPembayaran, self::Ditolak], true),
            self::Verifikasi => in_array($next, [self::MenungguPembayaran, self::Ditolak], true),
            self::MenungguPembayaran => in_array($next, [self::Aktif, self::Ditolak], true),
            self::Aktif, self::Ditolak, self::Dibatalkan => false,
        };
    }
}
