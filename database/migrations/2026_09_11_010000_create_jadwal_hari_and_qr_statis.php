<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Mirrors js/backend/prisma/migrations/20260911010000_jadwal_hari_and_qr_statis
// exactly, including the raw backfill SQL (kept as literal SQL rather than
// translated to the query builder, so the behavior - reading whatever the
// singleton `settings` row has via COALESCE - can't drift from the
// original).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_hari', function (Blueprint $table) {
            $table->integer('hari')->primary();
            $table->boolean('aktif')->default(true);
            $table->string('jamMasuk')->default('07:00');
            $table->string('batasTelat')->default('07:15');
            $table->string('jamPulang')->default('15:30');
            $table->dateTime('updatedAt', 3);
        });

        DB::unprepared(<<<'SQL'
            INSERT INTO `jadwal_hari` (`hari`, `aktif`, `jamMasuk`, `batasTelat`, `jamPulang`, `updatedAt`)
            SELECT 0, false, COALESCE(s.jamMasuk, '07:00'), COALESCE(s.batasTelat, '07:15'), COALESCE(s.jamPulang, '15:30'), NOW(3) FROM (SELECT 1 AS x) dummy LEFT JOIN `settings` s ON s.id = 1
            UNION ALL
            SELECT 1, true, COALESCE(s.jamMasuk, '07:00'), COALESCE(s.batasTelat, '07:15'), COALESCE(s.jamPulang, '15:30'), NOW(3) FROM (SELECT 1 AS x) dummy LEFT JOIN `settings` s ON s.id = 1
            UNION ALL
            SELECT 2, true, COALESCE(s.jamMasuk, '07:00'), COALESCE(s.batasTelat, '07:15'), COALESCE(s.jamPulang, '15:30'), NOW(3) FROM (SELECT 1 AS x) dummy LEFT JOIN `settings` s ON s.id = 1
            UNION ALL
            SELECT 3, true, COALESCE(s.jamMasuk, '07:00'), COALESCE(s.batasTelat, '07:15'), COALESCE(s.jamPulang, '15:30'), NOW(3) FROM (SELECT 1 AS x) dummy LEFT JOIN `settings` s ON s.id = 1
            UNION ALL
            SELECT 4, true, COALESCE(s.jamMasuk, '07:00'), COALESCE(s.batasTelat, '07:15'), COALESCE(s.jamPulang, '15:30'), NOW(3) FROM (SELECT 1 AS x) dummy LEFT JOIN `settings` s ON s.id = 1
            UNION ALL
            SELECT 5, true, COALESCE(s.jamMasuk, '07:00'), COALESCE(s.batasTelat, '07:15'), COALESCE(s.jamPulang, '15:30'), NOW(3) FROM (SELECT 1 AS x) dummy LEFT JOIN `settings` s ON s.id = 1
            UNION ALL
            SELECT 6, false, COALESCE(s.jamMasuk, '07:00'), COALESCE(s.batasTelat, '07:15'), COALESCE(s.jamPulang, '15:30'), NOW(3) FROM (SELECT 1 AS x) dummy LEFT JOIN `settings` s ON s.id = 1
        SQL);

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['jamMasuk', 'batasTelat', 'jamPulang']);
        });

        Schema::table('guru', function (Blueprint $table) {
            $table->string('qrToken')->nullable()->after('fotoUrl');
        });

        DB::statement("UPDATE `guru` SET `qrToken` = REPLACE(UUID(), '-', '') WHERE `qrToken` IS NULL");

        Schema::table('guru', function (Blueprint $table) {
            $table->string('qrToken')->nullable(false)->change();
            $table->unique('qrToken', 'guru_qrToken_key');
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropUnique('guru_qrToken_key');
            $table->dropColumn('qrToken');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('jamMasuk')->default('07:00');
            $table->string('batasTelat')->default('07:15');
            $table->string('jamPulang')->default('15:30');
        });

        Schema::dropIfExists('jadwal_hari');
    }
};
