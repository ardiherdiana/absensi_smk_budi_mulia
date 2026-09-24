<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencairans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->string('jenis');
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal');
            $table->string('dicairkan_oleh', 191);
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('dicairkan_oleh', 'pencairans_dicairkan_oleh_fkey')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairans');
    }
};
