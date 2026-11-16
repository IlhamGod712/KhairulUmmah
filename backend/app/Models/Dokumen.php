<?php

namespace App\Models;

use App\Enums\JenisDokumen;
use App\Enums\StatusDokumen;
use Database\Factories\DokumenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $santri_id
 * @property JenisDokumen|string $jenis
 * @property string $path
 * @property string|null $nama_asli
 * @property string|null $mime
 * @property string|null $mime_type
 * @property int|null $ukuran
 * @property int|null $ukuran_bytes
 * @property StatusDokumen|string $status
 * @property int|null $diverifikasi_oleh
 * @property Carbon|null $diverifikasi_pada
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Santri $santri
 * @property-read User|null $diverifikasiOleh
 */
#[Fillable([
    'santri_id',
    'jenis',
    'path',
    'nama_asli',
    'mime_type',
    'ukuran_bytes',
    'status',
    'diverifikasi_oleh',
    'diverifikasi_pada',
    'catatan',
])]
class Dokumen extends Model
{
    /** @use HasFactory<DokumenFactory> */
    use HasFactory;

    /**
     * Kept singular: Indonesian does not pluralise "dokumen", and the
     * pluralised guess would be "dokumens".
     *
     * @var string
     */
    protected $table = 'dokumen';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisDokumen::class,
            'status' => StatusDokumen::class,
            'ukuran_bytes' => 'integer',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function getUkuranAttribute(mixed $value = null): ?int
    {
        if (array_key_exists('ukuran_bytes', $this->attributes) && $this->attributes['ukuran_bytes'] !== null) {
            return (int) $this->attributes['ukuran_bytes'];
        }

        return null;
    }

    public function setUkuranAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['ukuran_bytes'] = null;

            return;
        }
        $this->attributes['ukuran_bytes'] = (int) $value;
    }

    public function getMimeAttribute(mixed $value = null): ?string
    {
        if (array_key_exists('mime_type', $this->attributes) && $this->attributes['mime_type'] !== null) {
            return (string) $this->attributes['mime_type'];
        }

        return null;
    }

    public function setMimeAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['mime_type'] = null;

            return;
        }
        $this->attributes['mime_type'] = (string) $value;
    }

    /**
     * @return BelongsTo<Santri, $this>
     */
    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    /**
     * The admin / TU who reviewed the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
