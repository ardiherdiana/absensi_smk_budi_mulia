<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mirrors js/backend/prisma/migrations/20260907081719_add_holiday
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->date('tanggal');
            $table->string('keterangan');
            $table->dateTime('createdAt', 3)->useCurrent();

            $table->unique('tanggal', 'holidays_tanggal_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
