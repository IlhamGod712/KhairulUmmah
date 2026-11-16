<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JenisPembayaran;
use App\Enums\StatusPembayaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterLaporanKeuanganRequest;
use App\Http\Resources\LaporanKeuanganResource;
use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminLaporanKeuanganController extends Controller
{
    public function index(FilterLaporanKeuanganRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = $this->buildQuery($validated);

        $perPage = (int) ($validated['per_page'] ?? 25);
        if ($perPage < 10) {
            $perPage = 10;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $paginator = (clone $query)
            ->with([
                'santri',
                'tahunAjaran',
            ])
            ->latest('id')
            ->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (Pembayaran $p) => (new LaporanKeuanganResource($p))->resolve($request));

        $summaryQuery = clone $query;
        $summary = $summaryQuery
            ->selectRaw('status, COUNT(*) as jumlah_transaksi, COALESCE(SUM(jumlah), 0) as total_nominal')
            ->groupBy('status')
            ->get();

        $totalSemua = (clone $query)->count();
        $totalNominalSemua = (clone $query)->sum(DB::raw('jumlah'));

        $terverifikasi = 0;
        $nominalTerverifikasi = 0.0;
        $menunggu = 0;
        $nominalMenunggu = 0.0;
        $ditolak = 0;
        $nominalDitolak = 0.0;

        foreach ($summary as $row) {
            $status = $row->status instanceof StatusPembayaran ? $row->status : StatusPembayaran::tryFrom((string) $row->status);
            $count = (int) $row->jumlah_transaksi;
            $nom = (float) $row->total_nominal;

            if ($status === StatusPembayaran::Lunas) {
                $terverifikasi += $count;
                $nominalTerverifikasi += $nom;
            } elseif ($status === StatusPembayaran::MenungguVerifikasi) {
                $menunggu += $count;
                $nominalMenunggu += $nom;
            } elseif ($status === StatusPembayaran::Ditolak) {
                $ditolak += $count;
                $nominalDitolak += $nom;
            } elseif ($status === StatusPembayaran::Belum) {
                // Belum bayar bukan pemasukan terverifikasi
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan keuangan berhasil dimuat.',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'ringkasan' => [
                    'total_transaksi' => $totalSemua,
                    'total_nominal' => number_format((float) $totalNominalSemua, 2, '.', ''),
                    'terverifikasi' => [
                        'jumlah' => $terverifikasi,
                        'nominal' => number_format($nominalTerverifikasi, 2, '.', ''),
                    ],
                    'menunggu_verifikasi' => [
                        'jumlah' => $menunggu,
                        'nominal' => number_format($nominalMenunggu, 2, '.', ''),
                    ],
                    'ditolak' => [
                        'jumlah' => $ditolak,
                        'nominal' => number_format($nominalDitolak, 2, '.', ''),
                    ],
                ],
            ],
        ]);
    }

    public function export(FilterLaporanKeuanganRequest $request): StreamedResponse
    {
        $validated = $request->validated();
        $query = $this->buildQuery($validated);

        $filename = 'laporan-keuangan-'.Carbon::now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.chr(34).$filename.chr(34),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'ID',
                'Tanggal Transaksi',
                'Jenis Pembayaran',
                'Periode',
                'NIS',
                'Nama Santri',
                'Nominal',
                'Status',
                'Metode',
                'Keterangan',
                'Diverifikasi Pada',
            ]);

            $query
                ->with([
                    'santri',
                ])
                ->latest('id')
                ->chunk(500, function ($rows) use ($file): void {
                    foreach ($rows as $pembayaran) {
                        /** @var Pembayaran $pembayaran */
                        $tanggal = $pembayaran->tanggal_bayar?->toDateString() ?? $pembayaran->divergisasi_pada?->toDateString() ?? $pembayaran->created_at?->toDateString();
                        $periode = $pembayaran->periode?->format('Y-m');
                        $santri = $pembayaran->relationLoaded('santri') ? $pembayaran->santri : null;
                        $nis = $santri?->nis;
                        $nama = $santri?->nama_lengkap;
                        $nominal = $this->csvSafe($pembayaran->jumlah);
                        $status = $pembayaran->status->label();
                        $metode = $pembayaran->metode?->label() ?? '';
                        $keterangan = $this->csvSafe($pembayaran->keterangan ?? '');
                        $divergisasi = $pembayaran->divergisasi_pada?->toDateTimeString() ?? '';

                        fputcsv($file, [
                            $pembayaran->id,
                            $tanggal ?? '',
                            $pembayaran->jenis_pembayaran->label(),
                            $periode ?? '',
                            $this->csvSafe((string) ($nis ?? '')),
                            $this->csvSafe((string) ($nama ?? '')),
                            $nominal,
                            $this->csvSafe($status),
                            $this->csvSafe($metode),
                            $keterangan,
                            $divergisasi,
                        ]);
                    }
                });

            fclose($file);
        };

        return response()->stream($callback, Response::HTTP_OK, $headers);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildQuery(array $validated): Builder
    {
        $query = Pembayaran::query();

        if (! empty($validated['tanggal_awal'])) {
            $query->where(function ($q) use ($validated): void {
                $q->whereDate('tanggal_bayar', '>=', $validated['tanggal_awal'])
                    ->orWhere(function ($qq) use ($validated): void {
                        $qq->whereNull('tanggal_bayar')
                            ->whereDate('divergisasi_pada', '>=', $validated['tanggal_awal']);
                    })
                    ->orWhere(function ($qq) use ($validated): void {
                        $qq->whereNull('tanggal_bayar')
                            ->whereNull('divergisasi_pada')
                            ->whereDate('created_at', '>=', $validated['tanggal_awal']);
                    });
            });
        }

        if (! empty($validated['tanggal_akhir'])) {
            $query->where(function ($q) use ($validated): void {
                $q->whereDate('tanggal_bayar', '<=', $validated['tanggal_akhir'])
                    ->orWhere(function ($qq) use ($validated): void {
                        $qq->whereNull('tanggal_bayar')
                            ->whereDate('divergisasi_pada', '<=', $validated['tanggal_akhir']);
                    })
                    ->orWhere(function ($qq) use ($validated): void {
                        $qq->whereNull('tanggal_bayar')
                            ->whereNull('divergisasi_pada')
                            ->whereDate('created_at', '<=', $validated['tanggal_akhir']);
                    });
            });
        }

        if (! empty($validated['jenis_pembayaran'])) {
            if ($validated['jenis_pembayaran'] instanceof JenisPembayaran) {
                $query->where('jenis_pembayaran', $validated['jenis_pembayaran']->value);
            } else {
                $query->where('jenis_pembayaran', $validated['jenis_pembayaran']);
            }
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] instanceof StatusPembayaran) {
                $query->where('status', $validated['status']->value);
            } else {
                $query->where('status', $validated['status']);
            }
        }

        return $query;
    }

    private function csvSafe(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            $prefix = chr(39);

            return $prefix.$value;
        }

        return $value;
    }
}
