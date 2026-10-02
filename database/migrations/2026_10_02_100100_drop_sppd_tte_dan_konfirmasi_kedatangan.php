<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur tanda tangan elektronik (TTE: QR, sidik jari, halaman verifikasi) dan konfirmasi kedatangan di tujuan
 * dihapus dari modul SPPD. Kolom `tte_*` dan tabel `konfirmasi_kedatangans` tidak lagi dipakai kode mana pun;
 * migrasi ini membuangnya (data TTE dan konfirmasi lama ikut hilang di basis data tempat migrasi dijalankan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('konfirmasi_kedatangans');

        Schema::table('pengajuan_sppds', function (Blueprint $table) {
            $table->dropUnique(['tte_kode']);
            $table->dropColumn(['tte_kode', 'tte_signature', 'tte_versi']);
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_sppds', function (Blueprint $table) {
            $table->string('tte_kode', 32)->nullable()->unique();
            $table->string('tte_signature', 64)->nullable();
            $table->unsignedTinyInteger('tte_versi')->default(1);
        });

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
};
