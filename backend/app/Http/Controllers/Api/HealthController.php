<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Proves the API layer, JSON envelope and CORS wiring work end to end before
 * any feature code is added.
 */
class HealthController extends Controller
{
    /**
     * Return service status plus the settings the SPA needs at boot.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Layanan berjalan',
            'data' => [
                'service' => config('app.name'),
                'environment' => app()->environment(),
                'api_prefix' => 'api',
            ],
        ]);
    }
}
