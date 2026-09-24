<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('briefing_attendance', function (Blueprint $table) {
            $table->dateTime('waktu', 3)->nullable()->change();
            $table->enum('status', ['HADIR', 'TELAT', 'IZIN', 'SAKIT', 'ALPA'])->nullable()->after('waktu');
            $table->string('catatan')->nullable()->after('status');
            $table->dateTime('updatedAt', 3)->nullable()->after('createdAt');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_attendance', function (Blueprint $table) {
            $table->dropColumn(['status', 'catatan', 'updatedAt']);
        });

        Schema::table('briefing_attendance', function (Blueprint $table) {
            $table->dateTime('waktu', 3)->nullable(false)->change();
        });
    }
};
