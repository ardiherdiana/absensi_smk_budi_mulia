<?php

namespace App\Models\Pguru\Concerns;

use Illuminate\Support\Str;

/**
 * Konvensi absensi: primary key ULID string, timestamp camelCase (createdAt/updatedAt).
 * Trait ini menggantikan blok yang sama yang diulang di tiap model absensi.
 */
trait HasUlid
{
    public function initializeHasUlid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    public static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            $model->id ??= (string) Str::ulid();
        });
    }

    public function getCreatedAtColumn(): string
    {
        return 'createdAt';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updatedAt';
    }
}
