<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_sppds', function (Blueprint $table) {
            $table->id();
            $table->string('pemohon_id', 191);
            $table->string('tujuan');
            $table->text('maksud');
            $table->string('alat_angkutan', 40)->nullable();
            $table->string('keterangan', 70)->nullable();
            $table->date('tanggal_berangkat');
            $table->time('jam_berangkat');
            $table->date('tanggal_kembali');
            $table->time('jam_kembali');
            $table->string('status')->default('draft');
            $table->string('undangan_path')->nullable();
            $table->text('catatan_kepsek')->nullable();
            $table->string('disetujui_oleh', 191)->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->string('tanda_tangan_kepsek_path')->nullable();
            $table->timestamp('ditolak_at')->nullable();
            $table->string('tte_kode', 32)->nullable()->unique();
            $table->string('tte_signature', 64)->nullable();
            $table->unsignedTinyInteger('tte_versi')->default(1);
            $table->timestamps();

            $table->foreign('pemohon_id', 'pengajuan_sppds_pemohon_id_fkey')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('disetujui_oleh', 'pengajuan_sppds_disetujui_oleh_fkey')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_sppds');
    }
};
