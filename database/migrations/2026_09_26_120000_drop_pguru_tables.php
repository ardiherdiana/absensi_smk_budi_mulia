<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Perangkat Guru" (API untuk aplikasi Expo SIMAK) dihapus dari absensi; SIMAK kini web Laravel sendiri dengan
 * basis data sendiri. Migrasi ini membuang tabel modul itu: kelas, siswa, nilai, dan supervisi (`pguru_*`),
 * `pguru_akun` bila masih ada dari versi lama, dan `personal_access_tokens` (Sanctum, hanya dipakai API itu).
 *
 * MENGHAPUS DATA di basis data tempat migrasi dijalankan, dan tidak bisa dipulihkan lewat `migrate:rollback`
 * (kodenya ada di riwayat git sebelum commit penghapusan, datanya tidak). Cadangkan tabel `pguru_*` lebih dulu bila
 * isinya masih diperlukan.
 *
 * Urutan: tabel anak dulu (nilai -> siswa -> kelas), lalu supervisi dan akun yang merujuk ke `users`/`pguru_akun`.
 * Aman dijalankan di basis data baru (tabelnya tidak ada; tidak terjadi apa-apa).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pguru_nilai', 'pguru_siswa', 'pguru_kelas', 'pguru_supervisi', 'pguru_akun', 'personal_access_tokens'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }

    public function down(): void
    {
        // Sengaja kosong: tabel dan datanya tidak dibuat ulang.
    }
};
