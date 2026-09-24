<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPPD's Laravel-standard notifications table, prefixed `sppd_` because absensi already owns a
     * `notifications` table with an unrelated, incompatible shape.
     */
    public function up(): void
    {
        Schema::create('sppd_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->string('notifiable_id', 191);
            $table->index(['notifiable_type', 'notifiable_id'], 'sppd_notifications_notifiable_type_id_index');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sppd_notifications');
    }
};
