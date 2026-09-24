<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_audits', function (Blueprint $table) {
            $table->id();
            $table->string('entitas_terkait');
            $table->unsignedBigInteger('entitas_id');
            $table->string('user_id', 191)->nullable();
            $table->string('aksi');
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entitas_terkait', 'entitas_id']);
            $table->foreign('user_id', 'log_audits_user_id_fkey')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_audits');
    }
};
