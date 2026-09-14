<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jadwal_hari', function (Blueprint $table) {
            // Earliest time absen masuk is allowed at all - separate from
            // `jamMasuk`, which stays the HADIR/TELAT reference point (still
            // compared only against `batasTelat`).
            $table->string('jamMulaiAbsen')->default('07:00')->after('aktif');
        });

        // Backfill from each row's own jamMasuk (not the flat '07:00'
        // default above) so any day already customized away from the
        // default keeps opening at exactly the same time it does today,
        // until an admin explicitly sets an earlier jamMulaiAbsen.
        DB::statement('UPDATE `jadwal_hari` SET `jamMulaiAbsen` = `jamMasuk`');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_hari', function (Blueprint $table) {
            $table->dropColumn('jamMulaiAbsen');
        });
    }
};
