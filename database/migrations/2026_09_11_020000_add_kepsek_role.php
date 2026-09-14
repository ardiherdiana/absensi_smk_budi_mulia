<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Mirrors js/backend/prisma/migrations/20260911020000_add_kepsek_role
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('ADMIN', 'GURU', 'KEPSEK') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('ADMIN', 'GURU') NOT NULL");
    }
};
