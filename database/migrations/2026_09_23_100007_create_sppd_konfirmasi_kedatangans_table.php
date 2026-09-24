<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konfirmasi_kedatangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->string('dikonfirmasi_oleh', 191);
            $table->string('pejabat_nama');
            $table->string('pejabat_jabatan');
            $table->date('tiba_tanggal');
            $table->date('berangkat_tanggal');
            $table->string('bukti_path')->nullable();
            $table->timestamps();

            $table->foreign('dikonfirmasi_oleh', 'konfirmasi_kedatangans_dikonfirmasi_oleh_fkey')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konfirmasi_kedatangans');
    }
};
