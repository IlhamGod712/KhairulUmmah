<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Documents a wali must attach before a student can be activated.
 */
enum JenisDokumen: string
{
    case KartuKeluarga = 'kk';
    case AktaLahir = 'akta_lahir';
    case Ijazah = 'ijazah';
    case IdentitasWali = 'identitas_wali';
    case PasFoto = 'pas_foto';

    /**
     * Proof that the registration fee was paid, uploaded after the registration
     * has been accepted.
     *
     * Lives in the same `dokumen` table as the identity documents — it is still
     * an uploaded file with a review status — but it is not a file a guardian
     * collects with the rest of the paperwork. It is supplied later, after the
     * registration is accepted, and it is not a precondition of *berkasLengkap*:
     * a hundred files cannot be "already complete" before the cash desk is even
     * in reach. `isRequired()` stays false so `berkasLengkap()` cannot be
     * satisfied by it.
     */
    case BuktiPembayaran = 'bukti_pembayaran';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::KartuKeluarga => 'Kartu Keluarga',
            self::AktaLahir => 'Akta Lahir',
            self::Ijazah => 'Ijazah / Rapor',
            self::IdentitasWali => 'Identitas Wali',
            self::PasFoto => 'Pas Foto',
            self::BuktiPembayaran => 'Bukti Pembayaran',
        };
    }

    /**
     * Whether the document is mandatory for activation.
     */
    public function isRequired(): bool
    {
        return match ($this) {
            self::KartuKeluarga, self::AktaLahir, self::Ijazah,
            self::IdentitasWali, self::PasFoto => true,
            self::BuktiPembayaran => false,
        };
    }
}
