<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAuditTrailRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class AdminAuditTrailController extends Controller
{
    public function index(IndexAuditTrailRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = AuditLog::query()
            ->with('pelaku:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['action']) && $filters['action'] !== '') {
            $query->where('action', 'like', '%'.$filters['action'].'%');
        }

        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);
        $logs = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Daftar audit trail.',
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('pelaku:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Detail audit trail.',
            'data' => new AuditLogResource($auditLog),
        ]);
    }
}
