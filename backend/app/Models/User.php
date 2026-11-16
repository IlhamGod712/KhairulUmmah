<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $no_hp
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 */
#[Fillable(['name', 'email', 'no_hp', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Whether the account's email address has been confirmed.
     *
     * Derived rather than stored as a status column: the only two states worth
     * distinguishing are "confirmed" and "not yet confirmed", and a second column
     * storing what `email_verified_at` already says would only be able to
     * disagree with it.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Determine whether the user administers the system as Admin / Tata Usaha.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Determine whether the user is a guardian of one or more students.
     */
    public function isWali(): bool
    {
        return $this->role === UserRole::Wali;
    }

    /**
     * Students this user is the guardian of.
     *
     * @return HasMany<Santri, $this>
     */
    public function santris(): HasMany
    {
        return $this->hasMany(Santri::class, 'wali_id');
    }

    /**
     * Payments this user recorded as admin / TU.
     *
     * @return HasMany<Pembayaran, $this>
     */
    public function pembayaransDicatat(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'dicatat_oleh');
    }

    /**
     * Documents this user reviewed as admin / TU.
     *
     * @return HasMany<Dokumen, $this>
     */
    public function dokumenDiverifikasi(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'diverifikasi_oleh');
    }
}
