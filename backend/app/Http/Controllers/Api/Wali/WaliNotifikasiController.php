<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotifikasiResource;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaliNotifikasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = Notifikasi::query()->where('user_id', $user->id)->orderByDesc('created_at');
        $items = $q->paginate(20)->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Daftar notifikasi.',
            'data' => [
                'items' => NotifikasiResource::collection($items->items())->resolve($request),
                'unread_count' => Notifikasi::query()->where('user_id', $user->id)->belumDibaca()->count(),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = Notifikasi::query()->where('user_id', $user->id)->belumDibaca()->count();

        return response()->json([
            'success' => true,
            'message' => 'Jumlah notifikasi belum dibaca.',
            'data' => ['count' => $count],
        ]);
    }

    public function markRead(Request $request, Notifikasi $notifikasi): JsonResponse
    {
        $user = $request->user();
        if ((int) $notifikasi->user_id !== (int) $user->id) {
            abort(404);
        }
        if (! $notifikasi->dibaca_pada) {
            $notifikasi->dibaca_pada = now();
            $notifikasi->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai dibaca.',
            'data' => (new NotifikasiResource($notifikasi))->resolve($request),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        Notifikasi::query()->where('user_id', $user->id)->whereNull('dibaca_pada')->update(['dibaca_pada' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi ditandai dibaca.',
            'data' => ['count' => 0],
        ]);
    }
}
