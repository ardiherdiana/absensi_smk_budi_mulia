# Absensi Guru - SMK Budi Mulia

Sistem absensi guru berbasis web untuk **SMK Budi Mulia, Karawang**. Guru absen masuk/pulang
lewat scan QR code, kepala sekolah dan admin memantau rekap kehadiran secara real-time, lengkap
dengan pengajuan izin/sakit dan absensi briefing pagi.

Dibangun dengan **Laravel** (backend) + **Inertia.js + React (TypeScript)** (frontend) sehingga
terasa seperti SPA tanpa perlu API terpisah.

## Fitur utama

- **Absen QR code** - guru scan QR unik miliknya sendiri untuk absen masuk & pulang, dengan
  deteksi keterlambatan otomatis berdasarkan jadwal per hari.
- **Absen briefing** - sesi absen terpisah untuk briefing pagi, dengan jam mulai/selesai yang
  bisa diatur per hari.
- **Multi-role**: `ADMIN`, `GURU`, `KEPSEK` (kepala sekolah) - tiap role punya dashboard dan menu
  sendiri.
- **Pengajuan izin/sakit** oleh guru, disetujui/ditolak oleh admin.
- **Rekap & export Excel** - laporan absensi dan briefing dengan kop surat sekolah, ringkasan,
  warna status per baris, dan siap cetak (`.xlsx`, dibangkitkan lewat PhpSpreadsheet).
- **Manajemen data guru & jadwal** oleh admin (jam masuk, batas telat, jam pulang, jam briefing
  per hari, hari libur).
- **Notifikasi push (PWA)** - aplikasi bisa di-install sebagai PWA dan mengirim notifikasi lewat
  Web Push (VAPID).
- **Lokasi sekolah & absen dari kiosk** - validasi lokasi untuk absen web, plus mode kiosk untuk
  scan QR di satu perangkat bersama.

## Tech stack

| Bagian    | Teknologi                                                              |
| --------- | ----------------------------------------------------------------------- |
| Backend   | Laravel 13, PHP 8.3+, MySQL                                             |
| Frontend  | React 19 + TypeScript, Inertia.js v3, Tailwind CSS v4, shadcn/ui        |
| Build     | Vite                                                                     |
| Lainnya   | PhpSpreadsheet (export Excel), minishlink/web-push (notifikasi push)    |

## Persyaratan

- PHP >= 8.3 beserta Composer
- Node.js (disarankan versi LTS terbaru) beserta npm
- MySQL (atau server database lain yang didukung Laravel)

## Instalasi & setup lokal

```bash
# 1. Install dependency PHP
composer install

# 2. Install dependency JavaScript
npm install

# 3. Salin file environment lalu sesuaikan isinya
cp .env.example .env
php artisan key:generate
```

Buka `.env` dan sesuaikan minimal bagian berikut:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` - kredensial MySQL lokal kamu.
- `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` - generate sendiri dengan
  `php artisan webpush:vapid` kalau ingin fitur notifikasi push aktif.
- `INERTIA_ENCRYPT_HISTORY=true` - **wajib tetap `true`**, ini yang mencegah tombol back browser
  menampilkan ulang halaman lama (form login basi atau dashboard akun lain) dari cache riwayat
  Inertia setelah login/logout.

Lanjutkan setup database:

```bash
# Buat semua tabel
php artisan migrate

# Isi data awal (akun admin, jadwal default, dll)
php artisan db:seed
```

## Menjalankan aplikasi (development)

```bash
composer dev
```

Perintah ini menjalankan server Laravel, queue listener, log viewer (Pail), dan Vite dev server
sekaligus. Aplikasi bisa diakses di `http://localhost:8000`.

Kalau ingin menjalankan manual satu-satu:

```bash
php artisan serve   # backend
npm run dev          # frontend (Vite, hot reload)
```

## Build untuk production

```bash
npm run build
php artisan config:cache
php artisan route:cache
```

## Testing & linting

```bash
composer test   # test suite PHP (PHPUnit)
npm run lint     # linting frontend (ESLint)
```

## Catatan keamanan

- **Jangan pernah commit file `.env`, `.env.local`, `.env.production`, atau dump database**
  (`database/seeders/data/production.sql`, file `.sqlite`). Semua sudah dicakup di `.gitignore` -
  hanya `.env.example` (isinya placeholder, tanpa kredensial asli) yang boleh masuk git.
- Foto guru dan lampiran izin yang di-upload (`storage/app/public/`) juga otomatis diabaikan git.
- Kalau butuh mengubah struktur environment, update `.env.example` juga supaya tetap jadi
  referensi yang akurat untuk setup berikutnya.
