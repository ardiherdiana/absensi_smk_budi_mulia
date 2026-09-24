<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('username');
            $table->string('password');
            $table->enum('role', ['ADMIN', 'GURU']);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique('username', 'users_username_key');
        });

        Schema::create('guru', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('userId', 191);
            $table->string('nip');
            $table->string('nama');
            $table->string('noHp')->nullable();
            $table->string('mapel')->nullable();
            $table->string('fotoUrl')->nullable();
            $table->boolean('aktif')->default(true);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique('userId', 'guru_userId_key');
            $table->unique('nip', 'guru_nip_key');
            $table->foreign('userId', 'guru_userId_fkey')
                ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('guruId', 191);
            $table->date('tanggal');
            $table->dateTime('jamMasuk', 3)->nullable();
            $table->dateTime('jamPulang', 3)->nullable();
            $table->enum('statusMasuk', ['HADIR', 'TELAT', 'ALPA', 'IZIN', 'SAKIT'])->nullable();
            $table->enum('statusPulang', ['HADIR', 'TELAT', 'ALPA', 'IZIN', 'SAKIT'])->nullable();
            $table->string('catatan')->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['guruId', 'tanggal'], 'attendance_guruId_tanggal_key');
            $table->foreign('guruId', 'attendance_guruId_fkey')
                ->references('id')->on('guru')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('guruId', 191);
            $table->enum('jenis', ['IZIN', 'SAKIT']);
            $table->date('tanggalMulai');
            $table->date('tanggalSelesai');
            $table->text('alasan');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->string('reviewedBy')->nullable();
            $table->dateTime('reviewedAt', 3)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->foreign('guruId', 'leave_requests_guruId_fkey')
                ->references('id')->on('guru')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->integer('id')->default(1)->primary();
            $table->string('namaSekolah')->default('Sekolah');
            $table->string('jamMasuk')->default('07:00');
            $table->string('batasTelat')->default('07:15');
            $table->string('jamPulang')->default('15:30');
            $table->dateTime('updatedAt', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('guru');
        Schema::dropIfExists('users');
    }
};
