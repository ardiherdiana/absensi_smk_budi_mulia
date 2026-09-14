<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['type', 'judul', 'pesan', 'guruId', 'isRead'])]
class Notification extends Model
{
    protected $table = 'notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            $notification->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'isRead' => 'boolean',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guruId');
    }
}
