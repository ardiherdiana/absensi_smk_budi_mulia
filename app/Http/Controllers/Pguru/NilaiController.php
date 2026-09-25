<?php

namespace App\Http\Controllers\Pguru;

use App\Models\Pguru\Nilai;
use App\Models\Pguru\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NilaiController extends PguruController
{
    /**
     * Simpan satu kolom nilai (mis. "tes3") untuk banyak siswa sekaligus, sesuai alur input di
     * aplikasi: pilih kelas, pilih kolom, isi nilai tiap siswa, simpan. `nilai: null` mengosongkan sel.
     */
    public function update(Request $request, string $kelasId): JsonResponse
    {
        $kelas = $this->kelasMilik($request, $kelasId);

        $data = $this->validasi($request, [
            'kolom' => ['required', 'string', Rule::in(Nilai::kolom())],
            'nilai' => ['required', 'array', 'min:1', 'max:200'],
            'nilai.*.siswaId' => ['required', 'string'],
            'nilai.*.nilai' => ['nullable', 'numeric', 'between:0,100'],
        ], [
            'kolom' => 'Kolom nilai',
            'nilai' => 'Nilai',
            'nilai.*.siswaId' => 'Siswa',
            'nilai.*.nilai' => 'Nilai',
        ]);

        $ids = collect($data['nilai'])->pluck('siswaId')->unique();
        $milikKelas = Siswa::where('kelasId', $kelas->id)->whereIn('id', $ids)->count();
        abort_if($milikKelas !== $ids->count(), 400, 'Ada siswa yang bukan bagian dari kelas ini');

        DB::transaction(function () use ($data) {
            foreach ($data['nilai'] as $item) {
                $nilai = Nilai::firstOrNew(['siswaId' => $item['siswaId']]);
                $nilai->{$data['kolom']} = $item['nilai'] === null ? null : round((float) $item['nilai'], 2);
                $nilai->save();
            }
        });

        return response()->json(['message' => 'Nilai tersimpan', 'diperbarui' => count($data['nilai'])]);
    }
}
