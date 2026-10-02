Ini repo Laravel 13 + Inertia + React (TypeScript) untuk sistem absensi guru SMK Budi Mulia Karawang. Repo yang sama memuat modul SPPD (surat perintah perjalanan dinas). Utamakan kebenaran data kehadiran, kesesuaian dengan kode di sekitarnya, dan antarmuka berbahasa Indonesia.

## Sebelum menulis kode

1. Baca `CLAUDE.md` di folder yang sama: isinya aturan yang tidak terlihat dari kode (konvensi model, alur permintaan, jebakan lingkungan). Lalu baca `README.md` untuk gambaran fitur, persyaratan, dan struktur. Tidak ada folder dokumen terpisah; selebihnya baca dari kode.
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
- **Basis data dipakai bersama absensi dan SPPD dan berisi data nyata.** Jangan menjalankan `migrate:fresh`, `migrate:refresh`, `db:wipe`, atau `db:seed --class=ProductionSyncSeeder` (menghapus isi tabel) tanpa izin eksplisit dari pemilik. `db:seed` biasa memakai sandi bawaan (`admin123`, `guru123`) dan hanya untuk pengembangan.
- **Migrasi tertunda di basis data dev**: `drop_pguru_tables` dan `drop_sppd_tte_dan_konfirmasi_kedatangan` membuang data. Jangan jalankan `php artisan migrate` polos; jalankan migrasi tambahan satu per satu dengan `--path` dan hanya bila diminta.
- **Skema SPPD ditulis dan dimigrasi dari repo ini.** Folder `../sppd` sudah dihapus; jangan mencari atau memigrasi ke sana.
- **Jangan meng-commit** `.env*` selain `.env.example`, dump basis data (`production.sql`, `*.sqlite`), atau paket unggahan (`app.zip` dan arsip lain di akar); semuanya sudah ter-ignore, jangan dipaksa dengan `git add -f`. Commit hanya bila diminta. Folder `laporan/` (laporan magang) sengaja tidak masuk git (`/laporan` di `.gitignore`). `.gitignore` bawaan Laravel di `storage/`, `bootstrap/cache/`, dan `database/` dipertahankan apa adanya.
- **Tampilan**: ikuti komponen shadcn/ui di `resources/js/components/ui` dan token warna di `resources/js/index.css`; jangan menulis warna mentah. Dua tema (terang dan gelap) harus sama-sama terbaca, teks antarmuka berbahasa Indonesia.
- **Konfirmasi**: jangan pakai `window.confirm`, `alert`, atau `prompt`. Pakai `useConfirm()` (`resources/js/hooks/use-confirm.tsx`, dialog shadcn). Menambah komponen shadcn dengan CLI bisa menimpa berkas dan menambah dependensi liar di `package.json`; periksa `git diff` sesudahnya.
- **Dua modul, dua layout**: halaman di bawah `pages/sppd/` memakai sidebar SPPD, sisanya sidebar absensi. Layar yang harus tampil di kedua modul (mis. profil) butuh rute dan halaman di masing-masing prefix supaya sidebar tidak berpindah modul.
- **Pesan galat** untuk pengguna berbahasa Indonesia, ditulis di tempat kode melempar `abort()` atau validasi, bukan di lapisan lain.
- **Perubahan di tabel `users`** berdampak ke absensi, SPPD, dan login web. Baca bagian "Shared database (SPPD)" di `CLAUDE.md` sebelum mengubah kolom atau peran, dan periksa pemakaiannya di kedua modul.
