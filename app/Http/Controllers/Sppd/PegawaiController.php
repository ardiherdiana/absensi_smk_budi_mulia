<?php

namespace App\Http\Controllers\Sppd;

use App\Enums\Sppd\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sppd\StorePegawaiRequest;
use App\Http\Requests\Sppd\UpdatePegawaiRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PegawaiController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('sppd/pegawai/index', [
            'items' => User::query()->with('roles')->latest()->paginate($this->perPage($request)),
            'roles' => array_map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()], RoleName::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sppd/pegawai/create', [
            'roles' => array_map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()], RoleName::cases()),
        ]);
    }

    public function store(StorePegawaiRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'jabatan' => $data['jabatan'] ?? null,
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles']);

        return to_route('sppd.pegawai.index')->with('success', 'Akun pegawai berhasil dibuat.');
    }

    public function edit(User $pegawai): Response
    {
        return Inertia::render('sppd/pegawai/edit', [
            'pegawai' => $pegawai->load('roles'),
            'roles' => array_map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()], RoleName::cases()),
        ]);
    }

    public function update(UpdatePegawaiRequest $request, User $pegawai): RedirectResponse
    {
        $data = $request->validated();

        $pegawai->update([
            'name' => $data['name'],
            'jabatan' => $data['jabatan'] ?? null,
            'username' => $data['username'],
            'is_active' => $data['is_active'],
        ]);

        $pegawai->syncRoles($data['roles']);

        return to_route('sppd.pegawai.index')->with('success', 'Akun pegawai berhasil diperbarui.');
    }

    public function destroy(User $pegawai): RedirectResponse
    {
        if ($pegawai->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $pegawai->delete();

        return back()->with('success', 'Akun pegawai dihapus.');
    }
}
