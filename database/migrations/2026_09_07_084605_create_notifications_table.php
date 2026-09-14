<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mirrors js/backend/prisma/migrations/20260907084605_add_notification
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->enum('type', ['CHECKIN', 'CHECKOUT', 'LEAVE_REQUEST']);
            $table->string('judul');
            $table->string('pesan');
            $table->string('guruId', 191)->nullable();
            $table->boolean('isRead')->default(false);
            $table->dateTime('createdAt', 3)->useCurrent();

            $table->index('isRead', 'notifications_isRead_idx');
            $table->foreign('guruId', 'notifications_guruId_fkey')
                ->references('id')->on('guru')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
