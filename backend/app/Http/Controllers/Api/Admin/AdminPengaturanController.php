<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePengaturanRequest;
use App\Models\Pengaturan;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPengaturanController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $keys = ['biaya_spp_bulanan', 'spp_tanggal_jatuh_tempo'];
        $items = Pengaturan::query()->whereIn('kunci', $keys)->get()->keyBy('kunci');

        $data = [];
        foreach ($keys as $k) {
            $data[$k] = isset($items[$k]) ? [
                'kunci' => $items[$k]->kunci,
                'nilai' => $items[$k]->nilai,
                'tipe' => $items[$k]->tipe,
            ] : [
                'kunci' => $k,
                'nilai' => $k === 'biaya_spp_bulanan' ? '0' : '10',
                'tipe' => $k === 'biaya_spp_bulanan' ? 'number' : 'number',
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan sistem.',
            'data' => $data,
        ]);
    }

    public function update(UpdatePengaturanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $pengaturan = $validated['pengaturan'] ?? [];

        $allowed = ['biaya_spp_bulanan', 'spp_tanggal_jatuh_tempo'];

        DB::transaction(function () use ($pengaturan, $allowed, $request): void {
            foreach ($pengaturan as $key => $value) {
                if (! in_array($key, $allowed, true)) {
                    continue;
                }

                if ($key === 'biaya_spp_bulanan') {
                    $nilai = number_format((float) $value, 2, '.', '');
                } else {
                    $hari = max(1, min(28, (int) $value));
                    $nilai = (string) $hari;
                }

                $nilaiSebelumnya = Pengaturan::query()->whereKey($key)->value('nilai');
                $item = Pengaturan::write($key, $nilai, 'number');

                if ($nilaiSebelumnya !== $nilai) {
                    $this->audit->log(
                        'pengaturan.updated',
                        'Admin memperbarui pengaturan sistem.',
                        $item,
                        ['kunci' => $key, 'nilai' => $nilaiSebelumnya],
                        ['kunci' => $key, 'nilai' => $nilai],
                        $request,
                    );
                }
            }
        });

        return $this->index($request);
    }
}
