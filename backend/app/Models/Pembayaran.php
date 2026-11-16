<?php

namespace App\Models;

use App\Enums\JenisPembayaran;
use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use Carbon\CarbonInterface;
use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $pendaftaran_id
 * @property int $santri_id
 * @property int $tahun_ajaran_id
 * @property JenisPembayaran $jenis_pembayaran
 * @property Carbon|null $periode
 * @property Carbon|null $jatuh_tempo
 * @property string $jumlah
 * @property MetodePembayaran|null $metode
 * @property StatusPembayaran $status
 * @property Carbon|null $tanggal_bayar
 * @property string|null $referensi
 * @property string|null $keterangan
 * @property string|null $bukti_path
 * @property string|null $bukti_nama_asli
 * @property string|null $bukti_mime_type
 * @property int|null $bukti_ukuran_bytes
 * @property int|null $dicatat_oleh
 * @property int|null $diverifikasi_oleh
 * @property Carbon|null $diverifikasi_pada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pendaftaran|null $pendaftaran
 * @property-read Santri $santri
 * @property-read TahunAjaran $tahunAjaran
 * @property-read User|null $dicatatOleh
 * @property-read User|null $diverifikasiOleh
 */
#[Fillable([
    'pendaftaran_id',
    'santri_id',
    'tahun_ajaran_id',
    'jenis_pembayaran',
    'periode',
    'jatuh_tempo',
    'jumlah',
    'metode',
    'status',
    'tanggal_bayar',
    'referensi',
    'keterangan',
    'bukti_path',
    'bukti_nama_asli',
    'bukti_mime_type',
    'bukti_ukuran_bytes',
    'dicatat_oleh',
    'diverifikasi_oleh',
    'diverifikasi_pada',
])]
class Pembayaran extends Model
{
    /** @use HasFactory<PembayaranFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // decimal:2 keeps the value as a string so no float rounding creeps in.
            'jumlah' => 'decimal:2',
            'jenis_pembayaran' => JenisPembayaran::class,
            'metode' => MetodePembayaran::class,
            'status' => StatusPembayaran::class,
            'tanggal_bayar' => 'date',
            'periode' => 'date',
            'jatuh_tempo' => 'date',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pendaftaran, $this>
     */
    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    /**
     * @return BelongsTo<Santri, $this>
     */
    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * The admin / TU who recorded the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    /**
     * The admin / TU who gave this invoice's proof its verdict.
     *
     * @return BelongsTo<User, $this>
     */
    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /**
     * Restrict to settled payments.
     *
     * @param  Builder<Pembayaran>  $query
     */
    #[Scope]
    protected function lunas(Builder $query): void
    {
        $query->where('status', StatusPembayaran::Lunas->value);
    }

    /**
     * Restrict to monthly SPP invoices, and only to real ones.
     *
     * This scope is the single definition of what counts as an SPP invoice, and
     * every SPP query in the application goes through it rather than repeating
     * the predicate. `jenis_pembayaran = 'spp'` alone is *not* enough: the Phase 6
     * migration defaulted that column to `spp`, so rows written before Phase 6
     * are typed `spp` while having no period, no due date, and whatever amount
     * they happened to carry. Requiring a `periode` is what separates a real
     * monthly invoice from those legacy rows — which is why the legacy data
     * needed no rewriting to stop being mistaken for SPP. They are inert here
     * and stay exactly as they are.
     *
     * @param  Builder<Pembayaran>  $query
     */
    #[Scope]
    protected function tagihanSpp(Builder $query): void
    {
        $query->where('jenis_pembayaran', JenisPembayaran::Spp->value)
            ->whereNotNull('periode');
    }

    /**
     * Whether this row is a monthly SPP invoice.
     */
    public function isTagihanSpp(): bool
    {
        return $this->jenis_pembayaran === JenisPembayaran::Spp && $this->periode !== null;
    }

    /**
     * The billed month as a human label, e.g. "Juli 2026".
     *
     * The year is part of the stored `periode` date rather than inferred from
     * the academic year, so the two are printed together and a reader can never
     * mistake a July in one school year for a July in the next. Returns NULL
     * for a row that is not a period invoice, so a non-SPP payment cannot be
     * given a month it was never billed for.
     */
    public function periodeLabel(): ?string
    {
        return self::labelPeriode($this->periode);
    }

    /**
     * A calendar month as a human label, e.g. "Juli 2026".
     *
     * The year is part of the stored `periode` date rather than inferred from
     * the academic year, so the two are printed together and a reader can never
     * mistake a July in one school year for a July in the next. Returns NULL for
     * a month that is not there, so a non-SPP payment cannot be given a period it
     * was never billed for.
     */
    public static function labelPeriode(?CarbonInterface $periode): ?string
    {
        if (! $periode instanceof CarbonInterface) {
            return null;
        }

        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $bulan[$periode->month].' '.$periode->year;
    }
}
