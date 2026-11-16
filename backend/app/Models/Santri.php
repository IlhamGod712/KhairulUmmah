<?php

namespace App\Models;

use App\Enums\JenisDokumen;
use App\Enums\JenisKelamin;
use App\Enums\SantriStatus;
use App\Enums\StatusDokumen;
use Database\Factories\SantriFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $wali_id
 * @property int $tahun_ajaran_id
 * @property string $nis
 * @property string|null $nisn
 * @property string $nama_lengkap
 * @property JenisKelamin $jenis_kelamin
 * @property string $tempat_lahir
 * @property Carbon $tanggal_lahir
 * @property string $alamat
 * @property string|null $kelurahan
 * @property string|null $kecamatan
 * @property string|null $kabupaten
 * @property string|null $provinsi
 * @property string|null $no_hp
 * @property string|null $nama_ayah
 * @property string|null $nama_ibu
 * @property string|null $pekerjaan_ayah
 * @property string|null $pekerjaan_ibu
 * @property int|null $penghasilan_bulanan
 * @property string|null $sekolah_asal
 * @property string|null $foto_path
 * @property SantriStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $wali
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Collection<int, Pendaftaran> $pendaftarans
 * @property-read Collection<int, Pembayaran> $pembayarans
 * @property-read Collection<int, Dokumen> $dokumen
 * @property-read Dokumen|null $buktiPembayaran
 * @property-read Collection<int, HistoriStatus> $historiStatus
 */
#[Fillable([
    'wali_id',
    'tahun_ajaran_id',
    'nis',
    'nisn',
    'nama_lengkap',
    'jenis_kelamin',
    'tempat_lahir',
    'tanggal_lahir',
    'alamat',
    'kelurahan',
    'kecamatan',
    'kabupaten',
    'provinsi',
    'no_hp',
    'nama_ayah',
    'nama_ibu',
    'pekerjaan_ayah',
    'pekerjaan_ibu',
    'penghasilan_bulanan',
    'sekolah_asal',
    'foto_path',
    'status',
])]
class Santri extends Model
{
    /** @use HasFactory<SantriFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_kelamin' => JenisKelamin::class,
            'tanggal_lahir' => 'date',
            'status' => SantriStatus::class,
            'penghasilan_bulanan' => 'integer',
        ];
    }

    /**
     * The guardian, a User with UserRole::Wali.
     *
     * @return BelongsTo<User, $this>
     */
    public function wali(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_id');
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * @return HasMany<Pendaftaran, $this>
     */
    public function pendaftarans(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }

    /**
     * @return HasMany<Pembayaran, $this>
     */
    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    /**
     * @return HasMany<Dokumen, $this>
     */
    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class);
    }

    /**
     * The uploaded proof of the registration fee, if any.
     *
     * The unique (santri_id, jenis) index on `dokumen` guarantees at most one
     * row per type, so a `hasOne` is the correct cardinality and an upload
     * "replaces" the file by rewriting this one row.
     *
     * @return HasOne<Dokumen, $this>
     */
    public function buktiPembayaran(): HasOne
    {
        return $this->hasOne(Dokumen::class)
            ->where('jenis', JenisDokumen::BuktiPembayaran);
    }

    /**
     * Audit trail of every status transition, oldest first.
     *
     * @return HasMany<HistoriStatus, $this>
     */
    public function historiStatus(): HasMany
    {
        return $this->hasMany(HistoriStatus::class)->oldest('pada');
    }

    /**
     * @return HasOne<Pendaftaran, $this>
     */
    public function pendaftaranTerbaru(): HasOne
    {
        return $this->hasOne(Pendaftaran::class)->latestOfMany('tanggal_daftar');
    }

    /**
     * The student's full name, shown wherever a label is needed.
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->nama_lengkap);
    }

    /**
     * Restrict to students whose status counts as active.
     *
     * @param  Builder<Santri>  $query
     */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('status', SantriStatus::Aktif->value);
    }

    /**
     * Whether every required document has been accepted.
     *
     * The unique (santri_id, jenis) index on `dokumen` guarantees at most one row
     * per type, so a count comparison is enough — no dedup needed.
     *
     * Only `isRequired()` types count. A `bukti_pembayaran` upload is not part
     * of the paperwork a guardian collects up front — it appears only once the
     * cash desk is in reach — so counting every `JenisDokumen::cases()` would
     * make a student permanently "not complete" before the fee was ever due,
     * and accepted afterwards, flipping `berkasLengkap()` in the wrong
     * direction as a side effect of paying.
     */
    public function berkasLengkap(): bool
    {
        $required = array_values(array_filter(
            JenisDokumen::cases(),
            fn (JenisDokumen $jenis): bool => $jenis->isRequired(),
        ));

        $acceptedRequired = $this->dokumen()
            ->whereIn('jenis', array_column($required, 'value'))
            ->where('status', StatusDokumen::Diterima->value)
            ->count();

        return $acceptedRequired === count($required);
    }
}
