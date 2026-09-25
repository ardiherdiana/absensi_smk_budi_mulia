<?php

namespace App\Http\Controllers\Pguru;

use App\Models\Pguru\Supervisi;
use App\Services\Pguru\PdfExporter;
use App\Services\Pguru\SupervisiDocxExporter;
use App\Support\Pguru\SupervisiInstrumen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SupervisiController extends PguruController
{
    private const ATRIBUT = [
        'guruDinilai' => 'Penyusun perencanaan',
        'pemberiUmpanBalik' => 'Pemberi umpan balik',
        'tanggal' => 'Tanggal',
        'butir' => 'Butir',
        'butir.*.nomor' => 'Nomor butir',
        'butir.*.bukti' => 'Bukti pembelajaran',
        'butir.*.catatan' => 'Catatan',
        'refleksi' => 'Refleksi',
        'refleksi.*' => 'Jawaban refleksi',
    ];

    public function __construct(private SupervisiDocxExporter $docx, private PdfExporter $pdf) {}

    /** Isi baku instrumen (butir 1-18) agar bentuk form di aplikasi selalu sama dengan template. */
    public function instrumen(): JsonResponse
    {
        return response()->json([
            'bagian' => SupervisiInstrumen::bagian(),
            'refleksi' => SupervisiInstrumen::refleksi(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $daftar = Supervisi::where('akunId', $this->akun($request)->id)
            ->orderByDesc('tanggal')->orderByDesc('createdAt')
            ->get(['id', 'guruDinilai', 'pemberiUmpanBalik', 'tanggal']);

        return response()->json(['data' => $daftar->map(fn (Supervisi $s) => [
            'id' => $s->id,
            'guruDinilai' => $s->guruDinilai,
            'pemberiUmpanBalik' => $s->pemberiUmpanBalik,
            'tanggal' => $s->tanggal->format('Y-m-d'),
        ])]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->milik($request, $id)->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->dataTervalidasi($request);

        $supervisi = Supervisi::create($data + ['akunId' => $this->akun($request)->id]);

        return response()->json(['data' => $supervisi->toApi()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $supervisi = $this->milik($request, $id);
        $supervisi->update($this->dataTervalidasi($request));

        return response()->json(['data' => $supervisi->toApi()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->milik($request, $id)->delete();

        return response()->json(['message' => 'Supervisi dihapus']);
    }

    public function export(Request $request, string $id): Response
    {
        $data = $this->validasi($request, ['format' => ['required', Rule::in(['docx', 'pdf'])]], ['format' => 'Format']);
        $supervisi = $this->milik($request, $id);
        $nama = 'Supervisi-'.Str::slug($supervisi->guruDinilai).'-'.$supervisi->tanggal->format('Y-m-d').'.'.$data['format'];

        if ($data['format'] === 'pdf') {
            return $this->pdf->supervisi($supervisi, $nama);
        }

        return response()->download($this->docx->buat($supervisi), $nama, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<string, mixed> */
    private function dataTervalidasi(Request $request): array
    {
        $data = $this->validasi($request, [
            'guruDinilai' => ['required', 'string', 'max:150'],
            'pemberiUmpanBalik' => ['required', 'string', 'max:150'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'butir' => ['nullable', 'array', 'max:'.SupervisiInstrumen::JUMLAH_BUTIR],
            'butir.*.nomor' => ['required', 'integer', 'between:1,'.SupervisiInstrumen::JUMLAH_BUTIR],
            'butir.*.bukti' => ['nullable', 'string', 'max:5000'],
            'butir.*.catatan' => ['nullable', 'string', 'max:5000'],
            'refleksi' => ['nullable', 'array', 'max:'.SupervisiInstrumen::JUMLAH_REFLEKSI],
            'refleksi.*' => ['nullable', 'string', 'max:5000'],
        ], self::ATRIBUT);

        return [
            'guruDinilai' => trim($data['guruDinilai']),
            'pemberiUmpanBalik' => trim($data['pemberiUmpanBalik']),
            'tanggal' => $data['tanggal'],
            'butir' => SupervisiInstrumen::lengkapiButir($data['butir'] ?? []),
            'refleksi' => SupervisiInstrumen::lengkapiRefleksi($data['refleksi'] ?? []),
        ];
    }

    private function milik(Request $request, string $id): Supervisi
    {
        return Supervisi::where('akunId', $this->akun($request)->id)->find($id)
            ?? abort(404, 'Supervisi tidak ditemukan');
    }
}
