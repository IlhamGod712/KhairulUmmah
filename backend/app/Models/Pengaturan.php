<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Key/value configuration edited by the admin, e.g. the school's DANA number.
 *
 * The key is the primary key: settings are looked up by name, never by a
 * surrogate id.
 *
 * @property string $kunci
 * @property string $nilai
 * @property string $tipe
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kunci', 'nilai', 'tipe'])]
class Pengaturan extends Model
{
    /**
     * Kept singular: "pengaturan" is a mass noun, so the pluralised guess reads
     * badly.
     *
     * @var string
     */
    protected $table = 'pengaturan';

    /**
     * @var string
     */
    protected $primaryKey = 'kunci';

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * Read a setting, falling back when the key is absent.
     */
    public static function read(string $kunci, string $default = ''): string
    {
        $nilai = static::query()
            ->whereKey($kunci)
            ->value('nilai');

        return $nilai ?? $default;
    }

    /**
     * Create or overwrite a setting.
     */
    public static function write(string $kunci, string $nilai, string $tipe = 'string'): self
    {
        return static::query()->updateOrCreate(
            ['kunci' => $kunci],
            ['nilai' => $nilai, 'tipe' => $tipe],
        );
    }
}
