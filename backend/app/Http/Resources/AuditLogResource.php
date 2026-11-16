<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Support\AuditSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'old_values' => AuditSanitizer::sanitize($this->old_values),
            'new_values' => AuditSanitizer::sanitize($this->new_values),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at->toISOString(),
            'pelaku' => $this->whenLoaded('pelaku', function (): ?array {
                if ($this->pelaku === null) {
                    return null;
                }

                return [
                    'id' => $this->pelaku->id,
                    'name' => $this->pelaku->name,
                ];
            }),
        ];
    }
}
