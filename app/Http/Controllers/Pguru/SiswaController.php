<?php

namespace App\Http\Controllers\Pguru;

use App\Models\Pguru\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiswaController extends PguruController
{
    public function index(Request $request, string $kelasId): JsonResponse
    {
        $kelas = $this->kelasMilik($request, $kelasId);

        return response()->json([
            'kelas' => $kelas->toApi(),
            'data' => $kelas->siswa()->with('nilai')->get()->map->toApi(),
        ]);
    }

    public function store(Request $request, string $kelasId): JsonResponse
    {
        $kelas = $this->kelasMilik($request, $kelasId);
        $data = $this->validasi($request, ['nama' => ['required', 'string', 'max:150']], ['nama' => 'Nama siswa']);

        $siswa = Siswa::create([
            'kelasId' => $kelas->id,
            'nomor' => ((int) $kelas->siswa()->max('nomor')) + 1,
            'nama' => $this->rapikan($data['nama']),
        ]);

        return response()->json(['data' => $siswa->toApi()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $siswa = $this->siswaMilik($request, $id);
        $data = $this->validasi($request, ['nama' => ['required', 'string', 'max:150']], ['nama' => 'Nama siswa']);

        $siswa->update(['nama' => $this->rapikan($data['nama'])]);

        return response()->json(['data' => $siswa->toApi()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->siswaMilik($request, $id)->delete();

        return response()->json(['message' => 'Siswa dihapus']);
    }

    private function siswaMilik(Request $request, string $id): Siswa
    {
        return Siswa::whereHas('kelas', fn ($q) => $q->where('akunId', $this->akun($request)->id))->find($id)
            ?? abort(404, 'Siswa tidak ditemukan');
    }

    private function rapikan(string $nama): string
    {
        return preg_replace('/\s+/', ' ', trim($nama));
    }
}
