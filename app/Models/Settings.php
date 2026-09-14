<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'namaSekolah'])]
class Settings extends Model
{
    protected $table = 'settings';

    // "id" is a fixed singleton row (always 1), not a real DB auto-increment
    // column - with $incrementing left true, Eloquent overwrites the id we
    // explicitly insert with MySQL's LAST_INSERT_ID() after create(), which
    // returns 0 since the column never actually auto-increments.
    public $incrementing = false;

    protected $keyType = 'int';

    const CREATED_AT = null;

    const UPDATED_AT = 'updatedAt';
}
