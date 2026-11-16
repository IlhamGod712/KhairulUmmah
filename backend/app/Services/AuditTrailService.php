<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Support\AuditSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AuditTrailService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(string $action, ?string $description = null, ?Model $subject = null, ?array $oldValues = null, ?array $newValues = null, ?Request $request = null): void
    {
        $request = $request ?? request();
        $user = $request->user();
        try {
            AuditLog::query()->create([
                'user_id' => $user?->getAuthIdentifier(),
                'action' => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'old_values' => AuditSanitizer::sanitize($oldValues),
                'new_values' => AuditSanitizer::sanitize($newValues),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'created_at' => Carbon::now(),
            ]);
        } catch (QueryException $exception) {
            Log::error('Audit log could not be persisted.', [
                'action' => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        }
    }
}
