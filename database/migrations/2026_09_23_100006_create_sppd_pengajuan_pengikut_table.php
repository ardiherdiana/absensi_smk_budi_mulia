<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_pengikut', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->string('user_id', 191);
            $table->unique(['pengajuan_id', 'user_id']);

            $table->foreign('user_id', 'pengajuan_pengikut_user_id_fkey')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_pengikut');
    }
};
