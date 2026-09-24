<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_hari', function (Blueprint $table) {
            $table->string('jamBriefing')->nullable()->after('jamPulang');
        });

        Schema::create('briefing_attendance', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('guruId', 191);
            $table->date('tanggal');
            $table->dateTime('waktu', 3);
            $table->dateTime('createdAt', 3)->useCurrent();

            $table->unique(['guruId', 'tanggal'], 'briefing_attendance_guruId_tanggal_key');
            $table->foreign('guruId', 'briefing_attendance_guruId_fkey')
                ->references('id')->on('guru')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_attendance');

        Schema::table('jadwal_hari', function (Blueprint $table) {
            $table->dropColumn('jamBriefing');
        });
    }
};
