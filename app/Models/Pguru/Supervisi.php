<?php

namespace App\Models\Pguru;

use App\Models\Pguru\Concerns\HasUlid;
use App\Models\User;
use App\Support\Pguru\SupervisiInstrumen;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['akunId', 'guruDinilai', 'pemberiUmpanBalik', 'tanggal', 'butir', 'refleksi'])]
class Supervisi extends Model
{
    use HasFactory, HasUlid;

    protected $table = 'pguru_supervisi';

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'butir' => 'array',
            'refleksi' => 'array',
        ];
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'akunId');
    }

    /**
     * Bentuk lengkap untuk klien: selalu 15 butir dan 3 jawaban refleksi, walau belum diisi.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'guruDinilai' => $this->guruDinilai,
            'pemberiUmpanBalik' => $this->pemberiUmpanBalik,
            'tanggal' => $this->tanggal->format('Y-m-d'),
            'butir' => SupervisiInstrumen::lengkapiButir($this->butir ?? []),
            'refleksi' => SupervisiInstrumen::lengkapiRefleksi($this->refleksi ?? []),
        ];
    }
}
