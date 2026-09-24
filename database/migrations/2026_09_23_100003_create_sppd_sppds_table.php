<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sppds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->string('nomor_sppd')->unique();
            $table->string('akun_anggaran', 100)->nullable();
            $table->string('diterbitkan_oleh', 191);
            $table->timestamps();

            $table->foreign('diterbitkan_oleh', 'sppds_diterbitkan_oleh_fkey')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sppds');
    }
};
