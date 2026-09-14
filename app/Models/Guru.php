<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['userId', 'nama', 'noHp', 'mapel', 'fotoUrl', 'aktif', 'qrToken'])]
class Guru extends Model
{
    protected $table = 'guru';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected static function booted(): void
    {
        static::creating(function (Guru $guru) {
            $guru->id ??= (string) Str::ulid();
            // Permanent unique token encoded into this guru's static QR code
            // - shown to the school's barcode/QR scanner station to record
            // absen masuk/pulang. Unlike the old rotating kiosk token, this
            // never expires on its own; it only changes if an admin
            // explicitly regenerates it (e.g. card lost).
            $guru->qrToken ??= bin2hex(random_bytes(16));
        });
    }

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'guruId');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'guruId');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'guruId');
    }

    public function briefingAttendances(): HasMany
    {
        return $this->hasMany(BriefingAttendance::class, 'guruId');
    }
}
