<?php

namespace App\Models\Pguru;

use App\Models\Pguru\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'siswaId',
    'tes1', 'tes2', 'tes3', 'tes4', 'tes5', 'tes6', 'tes7', 'tes8', 'tes9', 'tes10',
    'praktik1', 'praktik2', 'praktik3', 'praktik4', 'praktik5', 'praktik6', 'praktik7', 'praktik8', 'praktik9', 'praktik10',
])]
class Nilai extends Model
{
    use HasUlid;

    protected $table = 'pguru_nilai';

    public const JUMLAH = 10;

    /** @return list<string> tes1..tes10 */
    public static function kolomTes(): array
    {
        return array_map(fn (int $i) => "tes{$i}", range(1, self::JUMLAH));
    }

    /** @return list<string> praktik1..praktik10 */
    public static function kolomPraktik(): array
    {
        return array_map(fn (int $i) => "praktik{$i}", range(1, self::JUMLAH));
    }

    /** @return list<string> */
    public static function kolom(): array
    {
        return [...self::kolomTes(), ...self::kolomPraktik()];
    }

    /** @return array<string, null> */
    public static function kosong(): array
    {
        return array_fill_keys(self::kolom(), null);
    }

    protected function casts(): array
    {
        return array_fill_keys(self::kolom(), 'float');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswaId');
    }

    /** @return array<string, float|null> */
    public function toApi(): array
    {
        return collect(self::kolom())
            ->mapWithKeys(fn (string $k) => [$k => $this->getAttribute($k)])
            ->all();
    }
}
