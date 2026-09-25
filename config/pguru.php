<?php

/*
|--------------------------------------------------------------------------
| SIMAK (aplikasi mobile Expo, dulu "Perangkat Guru")
|--------------------------------------------------------------------------
| API di /api/pguru/*. Login memakai akun absensi (`users`); kelas, siswa, nilai, dan supervisi disimpan di
| tabel berawalan `pguru_` dan terikat ke `users.id` lewat kolom `akunId`. Tidak ada pendaftaran atau email.
*/

return [
    // Template resmi yang diisi apa adanya (bukan dibuat ulang) supaya hasil export 1:1.
    'templates' => [
        'nilai' => resource_path('pguru/format-nilai-siswa.xlsx'),
        'supervisi' => resource_path('pguru/format-supervisi-guru.docx'),
    ],

    'kota' => 'Karawang',
    'nama_sekolah' => 'SMK BUDI MULIA KARAWANG',
];
