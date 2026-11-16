<?php

namespace App\Models;

use App\Enums\StatusPendaftaran;
use Database\Factories\PendaftaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $santri_id
 * @property int $tahun_ajaran_id
 * @property string $nomor_pendaftaran
 * @property Carbon $tanggal_daftar
 * @property StatusPendaftaran $status
 * @property int|null $diproses_oleh
 * @property string|null $catatan
 * @property string|null $alasan_ditolak
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Santri $santri
 * @property-read TahunAjaran $tahunAjaran
 * @property-read User|null $diprosesOleh
 */
#[Fillable([
    'santri_id',
    'tahun_ajaran_id',
    'nomor_pendaftaran',
    'tanggal_daftar',
    'status',
    'diproses_oleh',
    'catatan',
    'alasan_ditolak',
])]
class Pendaftaran extends Model
{
    /** @use HasFactory<PendaftaranFactory> */
    use HasFactory;

    /**
     * Kept singular: "pendaftaran" reads better than the pluralised guess.
     *
     * @var string
     */
    protected $table = 'pendaftaran';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_daftar' => 'date',
            'status' => StatusPendaftaran::class,
        ];
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
     * The admin / TU who decided the submission.
     *
     * @return BelongsTo<User, $this>
     */
    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
