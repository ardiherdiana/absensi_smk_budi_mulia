Ini repo Laravel 13 + Inertia + React (TypeScript) untuk sistem absensi guru SMK Budi Mulia Karawang. Repo yang sama memuat dua modul lain: SPPD (surat perintah perjalanan dinas) dan API untuk aplikasi Android SIMAK (`/api/pguru`). Utamakan kebenaran data kehadiran, kesesuaian dengan kode di sekitarnya, dan antarmuka berbahasa Indonesia.

## Sebelum menulis kode

1. Baca `CLAUDE.md` di folder yang sama: isinya aturan yang tidak terlihat dari kode (konvensi model, alur permintaan, jebakan lingkungan). Lalu baca dokumen di folder `md/` yang relevan dengan pekerjaan (daftar di `README.md`). Folder `md/` sengaja tidak masuk git (`/md` di `.gitignore`), jadi mungkin tidak ada di salinan repo lain.
2. Berkas ini menggantikan stub pemasangan Laravel Boost bawaan starter. Laravel Boost belum terpasang (tidak ada di `composer.json`) dan `php artisan boost:install` akan menimpa berkas ini. Jangan memasangnya kecuali pemilik meminta.
3. Ikuti bentuk berkas tetangga: penamaan, kerapatan komentar, dan idiom yang sama. Bila berkas berbeda dari dokumen, ikuti berkas itu lalu perbaiki salah satunya.

## Perintah

Semua dijalankan dari akar repo.

```bash
composer dev                  # php artisan dev: server, antrean, Pail, dan Vite sekaligus
php artisan test              # PHPUnit (butuh MySQL hidup dan database absensi_testing)
./vendor/bin/pint             # format PHP (tanpa konfigurasi khusus, preset Laravel)
npx tsc -b                    # cek tipe frontend (sama dengan tahap pertama npm run build)
npm run lint                  # ESLint
npm run build                 # tsc -b lalu vite build (menulis ke public/build)
```

Tidak ada pengujian otomatis untuk frontend. Jalankan `npx tsc -b` dan `npm run lint` sebelum menyatakan pekerjaan frontend selesai, dan `php artisan test` untuk pekerjaan server.

## Aturan kerja

- **Jangan membiarkan dev server menyala.** Verifikasi lewat tes dan build. Bila perlu memeriksa di browser, nyalakan sebentar lalu matikan lagi.
- **Jangan membaca atau mencetak isi `.env*`.** Bila perlu memeriksa satu nilai, cari kuncinya saja (mis. `grep ^APP_ENV= .env`). Berkas itu berisi kunci Resend, `APP_KEY`, dan sandi basis data.
- **Basis data dipakai bersama tiga modul dan berisi data nyata.** Jangan menjalankan `migrate:fresh`, `migrate:refresh`, `db:wipe`, atau `db:seed --class=ProductionSyncSeeder` (menghapus isi tabel) tanpa izin eksplisit dari pemilik. `db:seed` biasa memakai sandi bawaan (`admin123`, `guru123`) dan hanya untuk pengembangan.
- **Skema SPPD dan Pguru ditulis dan dimigrasi dari repo ini.** Folder `../sppd` sudah dihapus; jangan mencari atau memigrasi ke sana.
- **Jangan meng-commit** `.env*` selain `.env.example`, dump basis data (`production.sql`, `*.sqlite`), atau paket unggahan (`app.zip` dan arsip lain di akar); semuanya sudah ter-ignore, jangan dipaksa dengan `git add -f`. Commit hanya bila diminta. Folder `md/` dan `laporan/` sengaja tidak masuk git (`/md` dan `/laporan` di `.gitignore`). `.gitignore` bawaan Laravel di `storage/`, `bootstrap/cache/`, dan `database/` dipertahankan apa adanya.
- **Tampilan**: baca `md/DESIGN_SYSTEM.md`. Warna lewat token, dua tema (terang dan gelap) harus sama-sama terbaca, teks antarmuka berbahasa Indonesia.
- **Pesan galat** untuk pengguna berbahasa Indonesia, ditulis di tempat kode melempar `abort()` atau validasi, bukan di lapisan lain.
- **Perubahan di tabel `users`** berdampak ke tiga modul (absensi, SPPD, dan login web). Periksa `md/SECURITY.md` bagian 4 dan `md/ARCHITECTURE.md` bagian 8 sebelum mengubah kolom atau peran.
