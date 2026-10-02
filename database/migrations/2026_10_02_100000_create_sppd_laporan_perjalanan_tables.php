<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_perjalanans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->string('ditulis_oleh', 191);
            $table->text('ringkasan');
            $table->timestamps();

            $table->foreign('ditulis_oleh', 'laporan_perjalanans_ditulis_oleh_fkey')
                ->references('id')->on('users');
        });

        Schema::create('laporan_perjalanan_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan_perjalanans')->cascadeOnDelete();
            $table->string('path');
            $table->string('nama_asli')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_perjalanan_fotos');
        Schema::dropIfExists('laporan_perjalanans');
    }
};
