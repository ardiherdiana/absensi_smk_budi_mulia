<?php

namespace App\Models\Pguru;

use App\Models\Pguru\Concerns\HasUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['akunId', 'nama', 'tahunAjaran'])]
class Kelas extends Model
{
    use HasFactory, HasUlid;

    protected $table = 'pguru_kelas';

    public function akun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'akunId');
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'kelasId')->orderBy('nomor');
    }

    /**
     * Jurusan diambil dari nama kelas "X TKJ 1" -> "TKJ". Null kalau formatnya tidak dikenali.
     */
    public function jurusan(): ?string
    {
        return self::jurusanDari($this->nama);
    }

    public static function jurusanDari(string $nama): ?string
    {
        return preg_match('/^(?:XII|XI|X)\s+(\S+)\s+\d+$/i', trim($nama), $m)
            ? strtoupper($m[1])
            : null;
    }

    /**
     * Pembanding urutan kelas: tingkat (X < XI < XII), jurusan, lalu nomor rombel sebagai angka
     * ("X TKJ 2" < "X TKJ 10"). strnatcasecmp() tidak cocok di sini karena mengabaikan spasi
     * sehingga "XII TKJ 1" terurut sebelum "X TKJ 1".
     */
    public static function urutAlami(self $a, self $b): int
    {
        return self::kunciUrut($a->nama) <=> self::kunciUrut($b->nama);
    }

    /** @return array{int, string, int, string} */
    private static function kunciUrut(string $nama): array
    {
        if (preg_match('/^(XII|XI|X)\s+(\S+)\s+(\d+)$/i', trim($nama), $m)) {
            return [['X' => 1, 'XI' => 2, 'XII' => 3][strtoupper($m[1])], strtoupper($m[2]), (int) $m[3], ''];
        }

        return [99, '', 0, mb_strtolower($nama)];
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'tahunAjaran' => $this->tahunAjaran,
            'jumlahSiswa' => $this->siswa_count ?? $this->siswa()->count(),
        ];
    }
}
