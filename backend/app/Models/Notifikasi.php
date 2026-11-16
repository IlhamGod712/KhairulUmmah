<?php

namespace App\Models;

use Database\Factories\NotifikasiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends Model
{
    /** @use HasFactory<NotifikasiFactory> */
    use HasFactory;

    protected $table = 'notifikasis';

    protected $fillable = [
        'user_id',
        'tipe',
        'judul',
        'pesan',
        'url',
        'data',
        'dibaca_pada',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'dibaca_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Notifikasi>  $query
     * @return Builder<Notifikasi>
     */
    public function scopeBelumDibaca($query): Builder
    {
        return $query->whereNull('dibaca_pada');
    }
}
