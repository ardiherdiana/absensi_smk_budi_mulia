<?php

namespace App\Http\Controllers\Pguru;

use App\Models\Pguru\Kelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KelasController extends PguruController
{
    private const ATRIBUT = ['nama' => 'Nama kelas', 'tahunAjaran' => 'Tahun ajaran'];

    public function index(Request $request): JsonResponse
    {
        $kelas = Kelas::where('akunId', $this->akun($request)->id)
            ->withCount('siswa')
            ->get()
            // Tahun ajaran terbaru dulu, lalu X < XI < XII, jurusan, nomor rombel.
            ->sort(fn (Kelas $a, Kelas $b) => strcmp($b->tahunAjaran, $a->tahunAjaran) ?: Kelas::urutAlami($a, $b))
            ->values();

        return response()->json(['data' => $kelas->map->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request, $this->aturan($request), self::ATRIBUT);

        $kelas = Kelas::create([
            'akunId' => $this->akun($request)->id,
            'nama' => $this->rapikan($data['nama']),
            'tahunAjaran' => $data['tahunAjaran'],
        ]);

        return response()->json(['data' => $kelas->toApi()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $kelas = $this->kelasMilik($request, $id);
        $data = $this->validasi($request, $this->aturan($request, $kelas), self::ATRIBUT);

        $kelas->update(['nama' => $this->rapikan($data['nama']), 'tahunAjaran' => $data['tahunAjaran']]);

        return response()->json(['data' => $kelas->toApi()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->kelasMilik($request, $id)->delete();

        return response()->json(['message' => 'Kelas dihapus']);
    }

    /** @return array<string, mixed> */
    private function aturan(Request $request, ?Kelas $kelas = null): array
    {
        $akunId = $this->akun($request)->id;

        return [
            'nama' => [
                'required', 'string', 'max:100',
                Rule::unique('pguru_kelas', 'nama')
                    ->where(fn ($q) => $q->where('akunId', $akunId)->where('tahunAjaran', $request->input('tahunAjaran')))
                    ->ignore($kelas?->id),
            ],
            'tahunAjaran' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
        ];
    }

    private function rapikan(string $nama): string
    {
        return preg_replace('/\s+/', ' ', trim($nama));
    }
}
