<?php

namespace App\Models;

use App\Enums\SantriStatus;
use Database\Factories\HistoriStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit trail of a student's status transitions.
 *
 * @property int $id
 * @property int $santri_id
 * @property SantriStatus|null $dari_status
 * @property SantriStatus $ke_status
 * @property string|null $keterangan
 * @property int|null $oleh
 * @property Carbon $pada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Santri $santri
 * @property-read User|null $olehOleh
 */
#[Fillable(['santri_id', 'dari_status', 'ke_status', 'keterangan', 'oleh', 'pada'])]
class HistoriStatus extends Model
{
    /** @use HasFactory<HistoriStatusFactory> */
    use HasFactory;

    /**
     * Kept singular: "histori_status" is a compound noun, not pluralisable.
     *
     * @var string
     */
    protected $table = 'histori_status';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dari_status' => SantriStatus::class,
            'ke_status' => SantriStatus::class,
            'pada' => 'datetime',
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
     * The user who performed the transition. Named to avoid clashing with the
     * `oleh` column.
     *
     * @return BelongsTo<User, $this>
     */
    public function olehOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
