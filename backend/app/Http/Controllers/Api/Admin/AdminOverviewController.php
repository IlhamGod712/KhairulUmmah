<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;

/**
 * A small Admin / Tata Usaha read model.
 *
 * Four institution-wide counts: the active academic year, the student roster,
 * the submissions, and the payment rows. It is a landing summary, not a report.
 * `total_pembayaran` counts every `pembayarans` row — registration fees and SPP
 * invoices alike — so it answers "how many payment records exist", not "how
 * much money was collected". The per-status and money breakdowns are Phase 9
 * work, not a promise this endpoint already keeps.
 */
class AdminOverviewController extends Controller
{
    /**
     * Return institution-wide counts.
     */
    public function __invoke(): JsonResponse
    {
        $tahunAjaran = TahunAjaran::active()->first();

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan admin berhasil dimuat.',
            'data' => [
                'tahun_ajaran_aktif' => $tahunAjaran?->nama,
                'total_santri' => Santri::query()->count(),
                'total_pendaftaran' => Pendaftaran::query()->count(),
                'total_pembayaran' => Pembayaran::query()->count(),
            ],
        ]);
    }
}
