<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('userId', 191);
            $table->string('endpoint', 500);
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            $table->dateTime('createdAt', 3)->useCurrent();

            $table->foreign('userId', 'push_subscriptions_userId_fkey')
                ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
