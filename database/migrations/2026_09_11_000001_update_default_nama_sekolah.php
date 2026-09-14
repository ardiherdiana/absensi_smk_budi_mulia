<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Mirrors js/backend/prisma/migrations/20260911000001_update_default_nama_sekolah
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `settings` ALTER COLUMN `namaSekolah` SET DEFAULT 'SMK Budi Mulia Karawang'");

        // Backfill: only rows still on the old generic placeholder, never an
        // admin's own custom value.
        DB::table('settings')
            ->where('namaSekolah', 'Sekolah')
            ->update(['namaSekolah' => 'SMK Budi Mulia Karawang']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `settings` ALTER COLUMN `namaSekolah` SET DEFAULT 'Sekolah'");
    }
};
