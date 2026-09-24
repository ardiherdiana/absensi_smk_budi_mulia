<?php

namespace App\Http\Controllers;

use App\Services\GuruService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function __construct(private GuruService $guruService) {}

    public function edit(Request $request)
    {
        if ($request->user()->role === 'ADMIN') {
            return Inertia::render('admin/profil-page');
        }

        abort_if(! $request->user()->guru, 403, 'Akun ini bukan akun guru');

        return Inertia::render('guru/profil-page');
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
