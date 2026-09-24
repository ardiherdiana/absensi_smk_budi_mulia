<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `settings` ALTER COLUMN `namaSekolah` SET DEFAULT 'SMK Budi Mulia Karawang'");

        DB::table('settings')
            ->where('namaSekolah', 'Sekolah')
            ->update(['namaSekolah' => 'SMK Budi Mulia Karawang']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `settings` ALTER COLUMN `namaSekolah` SET DEFAULT 'Sekolah'");
    }
};
