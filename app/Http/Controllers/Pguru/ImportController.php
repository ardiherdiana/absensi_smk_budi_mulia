<?php

namespace App\Http\Controllers\Pguru;

use App\Services\Pguru\SiswaImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends PguruController
{
    public function __construct(private SiswaImportService $import) {}

    public function siswa(Request $request): JsonResponse
    {
        $data = $this->validasi($request, [
            'berkas' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'tahunAjaran' => ['nullable', 'string', 'regex:/^\d{4}\/\d{4}$/'],
        ], ['berkas' => 'Berkas', 'tahunAjaran' => 'Tahun ajaran']);

        $hasil = $this->import->impor($this->akun($request), $request->file('berkas'), $data['tahunAjaran'] ?? null);

        return response()->json($hasil);
    }
}
