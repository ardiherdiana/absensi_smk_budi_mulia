<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Perangkat Guru" (API untuk aplikasi mobile Expo). Semua tabel berawalan `pguru_` dan
 * memakai gaya absensi: ULID string(191), kolom camelCase, createdAt/updatedAt.
 *
 * Akun sengaja TIDAK memakai `users` (dipakai bersama absensi + SPPD): pendaftaran terbuka di sini
 * tidak boleh menyentuh login absensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pguru_akun', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('name', 100);
            $table->string('email', 191)->unique('pguru_akun_email_key');
            $table->string('password');
            $table->string('role', 20)->default('GURU');
            $table->boolean('isActive')->default(true);
            $table->dateTime('emailVerifiedAt', 3)->nullable();
            $table->string('verifyCodeHash', 64)->nullable();
            $table->dateTime('verifyCodeExpiresAt', 3)->nullable();
            $table->dateTime('verifyCodeSentAt', 3)->nullable();
            $table->unsignedTinyInteger('verifyAttempts')->default(0);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);
        });

        Schema::create('pguru_kelas', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('akunId', 191);
            $table->string('nama', 100);
            $table->string('tahunAjaran', 9);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['akunId', 'tahunAjaran', 'nama'], 'pguru_kelas_akunId_tahunAjaran_nama_key');
            $table->foreign('akunId', 'pguru_kelas_akunId_fkey')
                ->references('id')->on('pguru_akun')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('pguru_siswa', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('kelasId', 191);
            $table->unsignedSmallInteger('nomor');
            $table->string('nama', 150);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->index(['kelasId', 'nomor'], 'pguru_siswa_kelasId_nomor_idx');
            $table->foreign('kelasId', 'pguru_siswa_kelasId_fkey')
                ->references('id')->on('pguru_kelas')->cascadeOnDelete()->cascadeOnUpdate();
        });

        // Satu baris per siswa; kolom mengikuti template (10 nilai tes teori + 10 nilai praktik).
        Schema::create('pguru_nilai', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('siswaId', 191)->unique('pguru_nilai_siswaId_key');
            foreach (['tes', 'praktik'] as $jenis) {
                for ($i = 1; $i <= 10; $i++) {
                    $table->decimal("{$jenis}{$i}", 5, 2)->nullable();
                }
            }
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->foreign('siswaId', 'pguru_nilai_siswaId_fkey')
                ->references('id')->on('pguru_siswa')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('pguru_supervisi', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('akunId', 191);
            $table->string('guruDinilai', 150);
            $table->string('pemberiUmpanBalik', 150);
            $table->date('tanggal');
            $table->json('butir');
            $table->json('refleksi');
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->index(['akunId', 'tanggal'], 'pguru_supervisi_akunId_tanggal_idx');
            $table->foreign('akunId', 'pguru_supervisi_akunId_fkey')
                ->references('id')->on('pguru_akun')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pguru_supervisi');
        Schema::dropIfExists('pguru_nilai');
        Schema::dropIfExists('pguru_siswa');
        Schema::dropIfExists('pguru_kelas');
        Schema::dropIfExists('pguru_akun');
    }
};
