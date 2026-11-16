<?php

namespace App\Models;

use Database\Factories\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Santri> $santris
 */
#[Fillable(['nama', 'tanggal_mulai', 'tanggal_selesai', 'is_active'])]
class TahunAjaran extends Model
{
    /** @use HasFactory<TahunAjaranFactory> */
    use HasFactory;

    /**
     * Kept singular: "tahun_ajaran" reads better than the pluralised guess.
     *
     * @var string
     */
    protected $table = 'tahun_ajaran';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Santri, $this>
     */
    public function santris(): HasMany
    {
        return $this->hasMany(Santri::class);
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
     * Restrict to the single academic year currently in use.
     *
     * @param  Builder<TahunAjaran>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
