<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPPD (a separate app) shares this `users` table for login; these are the columns its
     * profile/staff-management UI needs. `role` becomes nullable because SPPD-only staff (TU,
     * bendahara) may have no absensi role at all.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('username');
            $table->string('jabatan')->nullable()->after('role');
            $table->string('signature_path')->nullable()->after('jabatan');
            $table->boolean('is_active')->default(true)->after('signature_path');
        });

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('ADMIN', 'GURU', 'KEPSEK') NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name', 'jabatan', 'signature_path', 'is_active']);
        });

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('ADMIN', 'GURU', 'KEPSEK') NOT NULL");
    }
};
