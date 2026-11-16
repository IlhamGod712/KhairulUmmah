<?php

namespace App\Enums;

/**
 * What a payment is for.
 *
 * Phase 2 shipped `pembayarans` with no column to tell a registration fee apart
 * from a monthly SPP instalment, which is why the Phase 5 registration resource
 * could only report "payments recorded this year" and refused to claim the fee
 * was paid. Deciding *which* payment settles a registration is the whole point of
 * the accept -> pay -> activate pipeline, so the discriminator is added here
 * rather than inferred from the amount: an amount is not an identity, and the
 * school may change the fee while old rows keep their old number.
 */
enum JenisPembayaran: string
{
    /** One-off, tied to accepting a registration. Blocking for activation. */
    case Pendaftaran = 'pendaftaran';

    /** Recurring instalment. Never blocks activation on its own. */
    case Spp = 'spp';

    /**
     * Human readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendaftaran => 'Biaya Pendaftaran',
            self::Spp => 'SPP Bulanan',
        };
    }

    /**
     * Whether settling this payment is a precondition for the student becoming
     * active.
     *
     * The single place that answers it. Activation reads this rather than
     * comparing against `JenisPembayaran::Pendaftaran` directly, so adding a
     * third kind of fee later means deciding here, not in the activation path.
     */
    public function blocksActivation(): bool
    {
        return $this === self::Pendaftaran;
    }
}
