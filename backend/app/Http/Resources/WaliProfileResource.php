<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A guardian's own profile, as the guardian sees it.
 *
 * Exposes contact details only. Anything the guardian does not own — the user id
 * is included because the SPA keys nothing on it, but `role` is read-only here
 * and never accepted on the update path — is either absent or derived.
 *
 * @mixin User
 */
class WaliProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'no_hp' => $this->no_hp,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'email_verified' => $this->hasVerifiedEmail(),
            'email_verified_at' => $this->email_verified_at instanceof Carbon
                ? $this->email_verified_at->toIso8601String()
                : null,
            'created_at' => $this->created_at instanceof Carbon
                ? $this->created_at->toIso8601String()
                : null,
        ];
    }
}
