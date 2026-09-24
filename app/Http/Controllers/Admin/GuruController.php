<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Services\AttendanceService;
use App\Services\GuruService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GuruController extends Controller
{
    public function __construct(
        private GuruService $guruService,
        private AttendanceService $attendance,
    ) {}

    private function assertAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN', 403, 'Anda tidak memiliki akses');
    }

    public function index(Request $request)
    {
        return Inertia::render('admin/guru-page', [
            'guruList' => fn () => $this->guruService->list($request->query('search')),
            'search' => $request->query('search'),
        ]);
    }

    public function detail(Guru $guru)
    {
        return response()->json($this->attendance->guruDetail($guru->id));
    }

    public function store(Request $request)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'min:1'],
            'noHp' => ['nullable', 'string'],
            'mapel' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['nullable', 'in:GURU,KEPSEK'],
        ]);

        $this->guruService->create($validated);

        return back()->with('toast', ['type' => 'success', 'message' => 'Guru baru berhasil ditambahkan']);
    }

    public function update(Request $request, Guru $guru)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'nama' => ['sometimes', 'string', 'min:1'],
            'noHp' => ['nullable', 'string'],
            'mapel' => ['nullable', 'string'],
            'aktif' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['nullable', 'in:GURU,KEPSEK'],
        ]);

        $this->guruService->update($guru->id, $validated);

        return back()->with('toast', ['type' => 'success', 'message' => 'Data guru berhasil diperbarui']);
    }

    private function storeFoto(Request $request): string
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'foto.mimes' => 'Format foto harus JPG, PNG, atau WEBP',
            'foto.max' => 'Ukuran foto maksimal 2MB',
        ]);

        $filename = Str::ulid().'.'.$request->file('foto')->extension();
        $request->file('foto')->storeAs('guru', $filename, 'public');

        return "/uploads/guru/{$filename}";
    }

    public function uploadFoto(Request $request, Guru $guru)
    {
        $this->assertAdmin($request);

        if ($guru->fotoUrl && str_contains($guru->fotoUrl, '/uploads/guru/')) {
            Storage::disk('public')->delete('guru/'.basename($guru->fotoUrl));
        }
        $fotoUrl = $this->storeFoto($request);
        $this->guruService->update($guru->id, ['fotoUrl' => $fotoUrl]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Foto berhasil diperbarui']);
    }

    public function destroy(Request $request, Guru $guru)
    {
        $this->assertAdmin($request);

        $this->guruService->delete($guru->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun guru berhasil dihapus']);
    }

    public function regenerateQr(Request $request, Guru $guru)
    {
        $this->assertAdmin($request);

        return response()->json($this->guruService->regenerateQrToken($guru->id));
    }
}
