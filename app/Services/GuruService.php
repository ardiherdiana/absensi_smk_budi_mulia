<?php

namespace App\Services;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Mirrors backend/src/modules/guru/guru.service.ts exactly.
class GuruService
{
    /** Username is auto-derived from the teacher's own name (first two
     * words, lowercased, dot-joined - e.g. "Rio Falentino, S.Pd. Gr." ->
     * "rio.falentino") rather than a manually-assigned NIP, since there's no
     * other per-teacher identifier collected at creation time. Suffixed with
     * an incrementing number on collision. */
    private function baseUsernameFromNama(string $nama): string
    {
        $core = trim(explode(',', $nama)[0] ?? $nama);
        $words = array_slice(array_values(array_filter(preg_split('/\s+/', $core))), 0, 2);
        $slug = strtolower(implode('.', $words));
        $slug = preg_replace('/[^a-z0-9.]/', '', $slug);

        return $slug !== '' ? $slug : 'guru';
    }

    public function generateUniqueUsername(string $nama): string
    {
        $base = $this->baseUsernameFromNama($nama);
        $candidate = $base;
        $suffix = 2;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /** Mirrors guru.service.ts's `guruSelect` exactly - {id, nama, noHp,
     * mapel, fotoUrl, qrToken, aktif, createdAt, user: {username, role}} -
     * notably WITHOUT userId or updatedAt (guru) or id (nested user). */
    private function present(Guru $guru): array
    {
        return [
            'id' => $guru->id,
            'nama' => $guru->nama,
            'noHp' => $guru->noHp,
            'mapel' => $guru->mapel,
            'fotoUrl' => $guru->fotoUrl,
            'qrToken' => $guru->qrToken,
            'aktif' => $guru->aktif,
            'createdAt' => $guru->createdAt,
            'user' => [
                'username' => $guru->user->username,
                'role' => $guru->user->role,
            ],
        ];
    }

    public function list(?string $search = null): array
    {
        return Guru::with('user:id,username,role')
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->orderBy('nama')
            ->get()
            ->map(fn (Guru $guru) => $this->present($guru))
            ->all();
    }

    public function find(string $id): array
    {
        $guru = Guru::with('user:id,username,role')->find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }

        return $this->present($guru);
    }

    public function create(array $input): array
    {
        $username = $this->generateUniqueUsername($input['nama']);

        $user = User::create([
            'username' => $username,
            'password' => Hash::make($input['password']),
            'role' => $input['role'] ?? 'GURU',
        ]);

        $guru = Guru::create([
            'userId' => $user->id,
            'nama' => $input['nama'],
            'noHp' => $input['noHp'] ?? null,
            'mapel' => $input['mapel'] ?? null,
            'fotoUrl' => $input['fotoUrl'] ?? null,
            // Matches the "aktif" column's DB default (true) explicitly -
            // unlike Prisma's create(), Eloquent's create() doesn't reload
            // DB-applied defaults into the returned instance on its own.
            'aktif' => true,
        ])->load('user:id,username,role');

        return $this->present($guru);
    }

    public function update(string $id, array $input): array
    {
        $guru = Guru::find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }

        if (! empty($input['password']) || ! empty($input['role'])) {
            $guru->user->update(array_filter([
                'password' => ! empty($input['password']) ? Hash::make($input['password']) : null,
                'role' => $input['role'] ?? null,
            ]));
        }

        $guru->update(array_filter([
            'nama' => $input['nama'] ?? null,
            'noHp' => $input['noHp'] ?? null,
            'mapel' => $input['mapel'] ?? null,
            'fotoUrl' => $input['fotoUrl'] ?? null,
            'aktif' => array_key_exists('aktif', $input) ? $input['aktif'] : null,
        ], fn ($v) => $v !== null));

        return $this->present($guru->load('user:id,username,role'));
    }

    public function delete(string $id): void
    {
        $guru = Guru::find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }
        $guru->user->delete(); // cascades to guru via FK onDelete cascade
    }

    /** Issues a fresh static QR token for a guru, invalidating whatever QR
     * (card/phone) they had before - use when a card is lost or a token may
     * have been copied by someone else. */
    public function regenerateQrToken(string $id): array
    {
        $guru = Guru::find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }
        $guru->update(['qrToken' => bin2hex(random_bytes(16))]);

        return $this->present($guru->load('user:id,username,role'));
    }
}
