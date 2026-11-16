<?php

namespace App\Enums;

/**
 * Progress of a payment through review.
 *
 * Phase 2 shipped just `belum` and `lunas`. The accept -> pay -> activate
 * pipeline needs the two states in between: the guardian has supplied a proof
 * (`menunggu_verifikasi`), and the admin has turned the proof down
 * (`ditolak`), which reopens the chance to upload a corrected one. `lunas`
 * stays final — a paid fee is not un-paid.
 */
enum StatusPembayaran: string
{
    case Belum = 'belum';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Ditolak = 'ditolak';
    case Lunas = 'lunas';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum Bayar',
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Ditolak => 'Ditolak',
            self::Lunas => 'Lunas',
        };
    }

    /**
     * Whether the payment has been settled.
     */
    public function isPaid(): bool
    {
        return $this === self::Lunas;
    }

    /**
     * Whether a payment in this state may move to `$next`.
     *
     * The state machine, owned by the enum so every endpoint shares one map:
     *
     *   belum              -> menunggu_verifikasi   (guardian uploads a proof)
     *   menunggu_verifikasi-> lunas | ditolak        (admin reviews the proof)
     *   ditolak            -> menunggu_verifikasi   (guardian uploads a fixing)
     *   lunas              -> (terminal)
     *
     * Two illegal moves below are blocked here rather than in the controllers.
     * `ditolak -> lunas` would let a rejected proof still clear the fee, and any
     * path into `lunas` that skips `menunggu_verifikasi` would settle a payment
     * no one has ever seen.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Belum => $next === self::MenungguVerifikasi,
            self::MenungguVerifikasi => in_array($next, [self::Lunas, self::Ditolak], true),
            self::Ditolak => $next === self::MenungguVerifikasi,
            self::Lunas => false,
        };
    }
}
