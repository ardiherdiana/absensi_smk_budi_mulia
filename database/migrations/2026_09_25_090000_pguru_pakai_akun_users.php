<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SIMAK (API /api/pguru) berhenti memakai akun sendiri (`pguru_akun`, dengan pendaftaran dan verifikasi email) dan
 * memakai akun absensi (`users`: username + password). Kolom `akunId` di `pguru_kelas` dan `pguru_supervisi` kini
 * menunjuk ke `users.id` (sama-sama ULID string 191), lalu `pguru_akun` dan token Sanctum lamanya dihapus.
 *
 * Data lama tidak bisa dipetakan otomatis: id di `pguru_akun` tidak ada padanannya di `users`. Bila masih ada
 * kelas atau supervisi, migrasi BERHENTI sebelum mengubah apa pun dan menjelaskan pilihannya, supaya data tidak
 * hilang diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        $yatim = DB::table('pguru_kelas')->whereNotIn('akunId', DB::table('users')->select('id'))->count()
            + DB::table('pguru_supervisi')->whereNotIn('akunId', DB::table('users')->select('id'))->count();

        if ($yatim > 0) {
            throw new RuntimeException(
                "Ada {$yatim} kelas/lembar supervisi SIMAK milik akun lama (pguru_akun) yang tidak punya padanan di users. ".
                'Bila itu data uji, kosongkan tabel pguru_supervisi dan pguru_kelas (siswa dan nilai ikut terhapus) lalu jalankan migrasi lagi. '.
                'Bila itu data nyata, ubah kolom akunId di kedua tabel menjadi users.id pemiliknya lebih dulu.'
            );
        }

        Schema::table('pguru_kelas', fn (Blueprint $t) => $t->dropForeign('pguru_kelas_akunId_fkey'));
        Schema::table('pguru_supervisi', fn (Blueprint $t) => $t->dropForeign('pguru_supervisi_akunId_fkey'));

        // Token yang dibuat untuk model akun lama tidak berlaku lagi.
        DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\Pguru\\Akun')->delete();

        Schema::dropIfExists('pguru_akun');

        Schema::table('pguru_kelas', function (Blueprint $t) {
            $t->foreign('akunId', 'pguru_kelas_akunId_users_fkey')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
        Schema::table('pguru_supervisi', function (Blueprint $t) {
            $t->foreign('akunId', 'pguru_supervisi_akunId_users_fkey')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /** Mengembalikan bentuk tabel `pguru_akun` (kosong) dan kunci asingnya; akun dan data yang dihapus tidak kembali. */
    public function down(): void
    {
        Schema::table('pguru_kelas', fn (Blueprint $t) => $t->dropForeign('pguru_kelas_akunId_users_fkey'));
        Schema::table('pguru_supervisi', fn (Blueprint $t) => $t->dropForeign('pguru_supervisi_akunId_users_fkey'));

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

        Schema::table('pguru_kelas', function (Blueprint $t) {
            $t->foreign('akunId', 'pguru_kelas_akunId_fkey')->references('id')->on('pguru_akun')->cascadeOnDelete()->cascadeOnUpdate();
        });
        Schema::table('pguru_supervisi', function (Blueprint $t) {
            $t->foreign('akunId', 'pguru_supervisi_akunId_fkey')->references('id')->on('pguru_akun')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }
};
