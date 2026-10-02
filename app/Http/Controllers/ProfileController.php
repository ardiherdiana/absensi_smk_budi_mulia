<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\GuruService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function __construct(private GuruService $guruService, private AttendanceService $attendance) {}

    public function edit(Request $request)
    {
        if ($request->user()->role === 'ADMIN') {
            return Inertia::render('admin/profil-page', $this->ringkasan($request->user()));
        }

        abort_if(! $request->user()->guru, 403, 'Akun ini bukan akun guru');

        return Inertia::render('guru/profil-page', $this->ringkasan($request->user()));
    }

    /**
     * Profil di dalam modul SPPD (layout dan sidebar SPPD). Akun guru/kepsek melihat foto dan password;
     * akun lain (admin, staf yang hanya memakai SPPD) hanya melihat penggantian password.
     */
    public function editSppd(Request $request)
    {
        return Inertia::render('sppd/profil', ['punyaGuru' => $request->user()->guru !== null] + $this->ringkasan($request->user()));
    }

    /**
     * Isi halaman profil: data akun, data guru (bila ada), dan hitungan kehadiran bulan berjalan sampai hari ini.
     *
     * @return array{akun: array<string, mixed>, guru: ?array<string, mixed>, kehadiranBulanIni: ?array<string, int>}
     */
    private function ringkasan(User $user): array
    {
        $guru = $user->guru;

        $kehadiran = null;
        if ($guru) {
            $rows = $this->attendance->rekap(Carbon::today()->startOfMonth(), Carbon::today(), $guru->id);
            $kehadiran = array_merge(
                ['HADIR' => 0, 'TELAT' => 0, 'IZIN' => 0, 'SAKIT' => 0, 'ALPA' => 0],
                collect($rows)->pluck('status')->filter()->countBy()->all(),
            );
        }

        return [
            'akun' => [
                'username' => $user->username,
                'nama' => $user->name ?? $guru?->nama,
                'jabatan' => $user->jabatan,
                'peranAbsensi' => $user->role,
                'peranSppd' => $user->getRoleNames()->values()->all(),
                'bergabung' => $user->createdAt?->toDateString(),
            ],
            'guru' => $guru ? [
                'nama' => $guru->nama,
                'mapel' => $guru->mapel,
                'noHp' => $guru->noHp,
                'aktif' => (bool) $guru->aktif,
            ] : null,
            'kehadiranBulanIni' => $kehadiran,
        ];
    }

    public function uploadFoto(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'foto.mimes' => 'Format foto harus JPG, PNG, atau WEBP',
            'foto.max' => 'Ukuran foto maksimal 2MB',
        ]);

        if ($guru->fotoUrl && str_contains($guru->fotoUrl, '/uploads/guru/')) {
            Storage::disk('public')->delete('guru/'.basename($guru->fotoUrl));
        }

        $filename = Str::ulid().'.'.$request->file('foto')->extension();
        $request->file('foto')->storeAs('guru', $filename, 'public');

        $this->guruService->update($guru->id, ['fotoUrl' => "/uploads/guru/{$filename}"]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Foto profil berhasil diperbarui']);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'oldPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6'],
        ]);

        $user = $request->user();
        if (! Hash::check($validated['oldPassword'], $user->password)) {
            return back()->withErrors(['oldPassword' => 'Password lama salah']);
        }

        $user->update(['password' => Hash::make($validated['newPassword'])]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Password berhasil diganti']);
    }
}
