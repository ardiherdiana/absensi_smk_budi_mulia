# Absensi Guru - SMK Budi Mulia

Sistem absensi guru berbasis web untuk **SMK Budi Mulia, Karawang**. Guru absen masuk dan pulang lewat QR di perangkat scan sekolah (kiosk) atau lewat peramban dengan pemeriksaan lokasi. Admin dan kepala sekolah memantau rekap kehadiran, menyetujui izin dan sakit, dan mengatur jadwal. Aplikasinya bisa dipasang sebagai PWA dan mengirim pengingat lewat Web Push.

Satu repo dan satu basis data (`absensi_laravel`) memuat tiga bagian:

| Bagian | Alamat | Untuk siapa | Keterangan |
| --- | --- | --- | --- |
| Absensi (inti) | `/absen/dashboard` dan halaman lain di akar | `ADMIN`, `GURU`, `KEPSEK` | Absen QR dan web, briefing pagi, izin/sakit, rekap, jadwal, hari libur, notifikasi |
| SPPD | `/sppd/...` | Pemohon, kepala sekolah, TU, bendahara | Surat perintah perjalanan dinas: pengajuan, persetujuan dengan tanda tangan elektronik, penerbitan, uang muka, laporan. Ditandai "Sedang dalam pengembangan" di menu utama |
| API SIMAK | `/api/pguru/...` | Aplikasi Android SIMAK (`../perangkat-guru`) | Nilai siswa dan lembar supervisi guru. Akun terpisah dari `users` |

Setelah masuk, pengguna tiba di **Menu Utama** (`/menu-utama`) untuk memilih Absen atau SPPD.

## Fitur utama (absensi)

- **Absen lewat kiosk** - admin masuk di komputer sekolah dengan alat scan; guru menunjukkan QR pribadinya (permanen, dari HP atau kartu cetak) ke alat itu untuk absen masuk dan pulang. Terlambat atau tidak ditentukan otomatis dari jadwal hari itu.
- **Absen lewat web** - guru menekan tombol di peramban; server menerima koordinat dan menolak bila lebih dari 100 m dari sekolah.
- **Absen briefing** - sesi terpisah untuk briefing pagi, dipindai admin lewat kamera, dengan jam mulai dan selesai per hari.
- **Multi-role**: `ADMIN`, `GURU`, `KEPSEK` (kepala sekolah), masing-masing dengan menu sendiri.
- **Pengajuan izin/sakit** dengan lampiran oleh guru, disetujui atau ditolak admin atau kepala sekolah.
- **Rekap dan ekspor Excel** - laporan absensi dan briefing dengan kop sekolah, ringkasan, warna status per baris, siap cetak (`.xlsx`, PhpSpreadsheet).
- **Data guru, jadwal, dan hari libur** dikelola admin (jam mulai absen, jam masuk, batas telat, jam pulang, jam briefing per hari).
- **Notifikasi**: dalam aplikasi untuk admin/kepsek, dan pengingat Web Push (PWA, VAPID) 15 menit sebelum jam masuk dan pulang untuk guru.

## Tech stack

| Bagian | Teknologi |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+, MySQL |
| Frontend | React 19 + TypeScript, Inertia.js v3, Tailwind CSS v4, shadcn/ui |
| Build | Vite |
| Autentikasi | Sesi (web), Sanctum (API SIMAK), spatie/laravel-permission (peran SPPD) |
| Lainnya | PhpSpreadsheet (Excel), dompdf (PDF), minishlink/web-push (push), Resend (email kode SIMAK) |

## Persyaratan

- PHP 8.3 atau lebih baru (mesin pengembangan memakai 8.4) dan Composer
- Node.js LTS dan npm
- MySQL. Migrasi memakai DDL khusus MySQL (`NOW(3)`, `UUID()`, `MODIFY COLUMN ... ENUM`), jadi SQLite tidak bisa dipakai, termasuk untuk tes

## Menjalankan

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate      # lalu isi DB_DATABASE, DB_USERNAME, DB_PASSWORD di .env
php artisan migrate
php artisan db:seed           # hanya pengembangan: akun contoh, lihat di bawah
composer dev                  # php artisan dev: server (:8000), antrean, Pail, dan Vite
```

Pintasan pemasangan awal: `composer setup` (install, salin `.env`, `key:generate`, `migrate --force`, `npm install`, `npm run build`). Aplikasi terbuka di `http://localhost:8000`. Untuk menjalankan satu-satu: `php artisan serve` (backend) dan `npm run dev` (Vite).

Akun contoh dari `db:seed` (jangan dipakai di produksi): `admin` / `admin123`, `kepsek` / `kepsek123`, dan 23 guru dengan sandi `guru123` (nama pengguna berbentuk `rio.falentino`). Seeder yang sama membuat empat peran SPPD (`pemohon`, `kepala_sekolah`, `tu`, `bendahara`). Untuk bekerja dengan data nyata: `php artisan db:seed --class=ProductionSyncSeeder` (menghapus isi tabel lalu memuat `database/seeders/data/production.sql`; jangan dijalankan bersama `db:seed` biasa).

Variabel `.env` yang perlu diperhatikan:

| Variabel | Fungsi |
| --- | --- |
| `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id` | Semua jam absen dihitung dalam zona ini |
| `INERTIA_ENCRYPT_HISTORY=true` | **Wajib `true` di semua lingkungan.** Mencegah tombol kembali menampilkan halaman lama (form masuk basi atau dashboard akun lain) dari riwayat Inertia setelah masuk atau keluar |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | Web Push. Buat dengan `php artisan webpush:vapid`. Kosong berarti push mati tanpa galat |
| `OPENSSL_CONF` | Hanya di Windows bila push gagal diam-diam; arahkan ke `openssl.cnf` milik Git for Windows |
| `SANCTUM_EXPIRATION` | Masa berlaku token API SIMAK (menit), bawaan 90 hari. API SIMAK memakai akun `users` yang sama dengan web; `PGURU_MAIL_*` dan `RESEND_API_KEY` tidak dipakai lagi |
| `TTE_SECRET` | Kunci tanda tangan elektronik SPPD. Kosong berarti memakai `APP_KEY`. Belum ada di `.env.example` |

Pengingat absen memakai penjadwal Laravel (`attendance:send-reminders` tiap menit). Di server, pasang cron `* * * * * php artisan schedule:run`.

## Build untuk produksi

```bash
npm run build                 # tsc -b lalu vite build, menulis public/build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan storage:link
```

Pasang juga cron `schedule:run` (di atas). Titik pemeriksaan kesehatan: `/up`. Produksi harus berjalan dengan `APP_ENV=production`, `APP_DEBUG=false`, dan `INERTIA_ENCRYPT_HISTORY=true`.

## Testing dan linting

```bash
php artisan test              # butuh MySQL hidup dan database absensi_testing (sekali: CREATE DATABASE absensi_testing;)
composer test                 # sama, dengan config:clear lebih dulu
./vendor/bin/pint --test      # format PHP
npx tsc -b                    # tipe frontend
npm run lint                  # ESLint
```

Tidak ada uji otomatis untuk frontend; `tsc -b` dan ESLint adalah satu-satunya pemeriksaannya. Tidak perlu dev server untuk semua pemeriksaan di atas.

## Struktur

```
app/Http/Controllers/       Admin/, Auth/, Guru/, Sppd/, Pguru/ dan controller absensi di akar
app/Http/Middleware/        RequireRole (role:), HandleInertiaRequests, PreventBackHistoryCache, Sppd/, Pguru/
app/Models/                 model absensi di akar, Sppd/, Pguru/
app/Services/               logika bisnis (AttendanceService, BriefingService, LeaveService, ...), Sppd/, Pguru/
app/Support/                Geofence, XlsxExport, Pguru/
app/Console/Commands/       SendAttendanceReminders, PguruBuatAdmin
database/migrations|seeders|factories
routes/                     web.php (absensi + SPPD), api.php (Pguru), console.php (penjadwal)
resources/js/               main.tsx, pages/, layouts/, components/ (ui/ = shadcn), lib/, context/, types/
resources/views/            app.blade.php, pdf/ (SPPD, laporan, supervisi), mail/
resources/pguru/            template xlsx dan docx untuk API SIMAK
public/                     sw.js, manifest.json, ikon PWA, logo_smk.png
tests/                      Feature/ (Admin, Attendance, Auth, Briefing, Leave, Middleware, Sppd, Pguru, ...) dan Unit/
md/                         dokumen proyek (tidak masuk git)
```

## Dokumentasi

Dokumen proyek ada di folder `md/` (lokal, tidak ikut git):

| Berkas | Isi |
| --- | --- |
| `md/PRD.md` | Tujuan, pengguna, ruang lingkup, kebutuhan fungsional per bagian, aturan bisnis, status dan risiko |
| `md/ARCHITECTURE.md` | Alur permintaan, peran dan otorisasi, frontend, domain kehadiran, data, modul SPPD dan API SIMAK, konfigurasi |
| `md/DESIGN_SYSTEM.md` | Token warna terang/gelap, cara kerja tema, komponen, kontras terukur |
| `md/SECURITY.md` | Data yang dilindungi, kontrol yang ada, celah yang diketahui, daftar periksa rilis |
| `md/CODE_STYLE.md` | Konvensi PHP dan TypeScript, model dan migrasi, galat, komentar |
| `md/TESTING.md` | Pemeriksaan statis, tes PHPUnit per area, daftar uji manual, yang belum diuji |

Petunjuk untuk asisten pemrograman ada di `AGENTS.md` dan `CLAUDE.md` (akar repo). Aplikasi Android SIMAK punya dokumen sendiri di `../perangkat-guru/md/`.

## Catatan keamanan

- **Jangan pernah commit `.env`, `.env.local`, `.env.production`, dump basis data (`database/seeders/data/production.sql`, `*.sqlite`), atau paket unggahan (`app.zip` dan arsip lain).** Semuanya sudah dicakup `.gitignore`; hanya `.env.example` (isinya placeholder, tanpa kredensial asli) yang boleh masuk git.
- Foto guru dan lampiran izin yang diunggah (`storage/app/public/`) juga otomatis diabaikan git.
- Kalau struktur environment berubah, perbarui `.env.example` supaya tetap jadi referensi yang akurat.
- Daftar celah keamanan yang diketahui dan belum ditutup ada di `md/SECURITY.md` bagian 7.
