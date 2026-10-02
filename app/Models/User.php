<?php

namespace App\Models;

use App\Models\Sppd\PengajuanSppd;
use App\Models\Sppd\SppdNotification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['username', 'password', 'role', 'name', 'jabatan', 'is_active', 'signature_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class, 'userId');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'userId');
    }

    /**
     * Overrides Notifiable's default so SPPD notifications land in `sppd_notifications` instead of
     * absensi's own, differently-shaped `notifications` table (see App\Models\Notification).
     *
     * @return MorphMany<SppdNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(SppdNotification::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * @return HasMany<PengajuanSppd, $this>
     */
    public function pengajuanSppds(): HasMany
    {
        return $this->hasMany(PengajuanSppd::class, 'pemohon_id');
    }

    /**
     * Pegawai yang dapat dipilih (mis. sebagai pengikut SPPD): akun aktif yang memiliki minimal satu
     * role SPPD.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function pegawaiAktif(Builder $query): void
    {
        $query->where('is_active', true)->has('roles');
    }

    /**
     * Akun absensi `ADMIN` — lolos semua pengecekan Policy & middleware role modul SPPD tanpa perlu
     * role pemohon/kepala_sekolah/tu/bendahara (lihat Gate::before di AppServiceProvider dan
     * App\Http\Middleware\Sppd\RoleMiddleware).
     */
    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }
}
