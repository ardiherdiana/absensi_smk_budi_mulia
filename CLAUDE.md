# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

## Project overview

**Absensi Guru** — a teacher attendance system for SMK Budi Mulia, Karawang (Indonesian vocational
high school). Teachers (`GURU`) check in/out via QR code scan or geofenced web check-in; admins
(`ADMIN`) and the headmaster (`KEPSEK`) monitor attendance recaps in real time, approve leave
requests, and manage schedules. Everything is in Indonesian (UI text, route names, validation
messages, DB column values like `HADIR`/`TELAT`/`IZIN`/`SAKIT`/`ALPA`).

Backend is Laravel; frontend is Inertia.js + React (TypeScript) — server-rendered routing with a
React SPA feel, no separate REST API layer for pages.

The same repo and the same database (`absensi_laravel`) also host the **SPPD module** (surat perintah
perjalanan dinas: `/sppd/*` routes, `App\...\Sppd` namespaces, see "Shared database (SPPD)" below). Everything
above and below that is not labelled SPPD describes the attendance core.

SIMAK (nilai siswa + supervisi guru, dulu modul `Pguru` dengan API `/api/pguru` di repo ini) sudah dipisah menjadi
proyek Laravel sendiri (`../simak`, basis data sendiri). Modulnya dihapus dari sini 2026-09-26; migrasi
`2026_09_26_120000_drop_pguru_tables` membuang tabel `pguru_*` dan `personal_access_tokens` (data ikut hilang di basis data
tempat migrasi dijalankan). Kodenya masih ada di riwayat git sebelum commit penghapusan.

Tidak ada folder dokumen terpisah: aturan dan konvensi proyek ada di berkas ini, `AGENTS.md`, dan `README.md`;
sisanya baca dari kode. Folder `laporan/` (laporan magang) dan berkas pribadi lain sengaja di-`.gitignore` dan bukan
bagian aplikasi. (Proyek ini dulu berada di subfolder `laravel/` di bawah akar git; sekarang akar repo dan akar
aplikasi Laravel adalah folder yang sama.) Seeder dev yang sebenarnya ada di `database/seeders/DatabaseSeeder.php`
(lihat "Seeding").

## Commands

All commands run from the repo root.

```bash
composer dev              # runs php artisan serve + queue listener + Pail (log viewer) + vite, all at once
php artisan serve         # backend only
npm run dev                # frontend only (Vite, hot reload)

composer test              # PHPUnit (clears config cache first)
php artisan test                              # same, direct
php artisan test --filter=TestName            # run a single test
php artisan test tests/Feature/SomeTest.php   # run a single file

npm run lint                # ESLint over resources/js
npm run build                # tsc -b && vite build (type-checks as part of build)
./vendor/bin/pint            # PHP formatting (Laravel Pint, no custom config)

php artisan migrate
php artisan db:seed                                  # fake dev data (admin/kepsek/guru roster, see below)
php artisan db:seed --class=ProductionSyncSeeder      # real prod data dump instead (see below)
```

There is no frontend test runner (no vitest/jest) — `npm run lint` and the `tsc -b` type-check in
`npm run build` are the only frontend checks.

## Architecture

### Data model conventions (non-standard for Laravel — attendance core)

Every attendance-core model in `app/Models` deviates from Laravel
defaults the same way. The SPPD models under `Models/Sppd` are the exception: they use auto-increment `id`,
snake_case columns and `created_at`/`updated_at`, with user-reference columns kept as `string(191)` to match
`users.id`. Don't mix the two styles inside one module.

- **String ULID primary keys**, not auto-increment ints: `public $incrementing = false`,
  `protected $keyType = 'string'`, and an `id` assigned via `Str::ulid()` in a `static::creating`
  hook.
- **camelCase DB columns**, not snake_case (`guruId`, `jamMasuk`, `tanggalMulai`, `createdAt`...).
  Timestamps are remapped with `const CREATED_AT = 'createdAt'` / `const UPDATED_AT = 'updatedAt'`.
- **PHP attributes instead of properties** for `$fillable`/`$hidden`: `#[Fillable([...])]` and
  `#[Hidden([...])]` above the class, not `protected $fillable = [...]`.

Match this style for any new model or migration — mixing snake_case columns into this schema will
break relations and casts silently.

### Request flow: routes → controllers → services

- `routes/web.php` is grouped by `role:X,Y` middleware (aliased to `App\Http\Middleware\RequireRole`
  in `bootstrap/app.php`) — e.g. `role:ADMIN`, `role:ADMIN,KEPSEK`, `role:GURU,KEPSEK`. When adding
  an endpoint, put it in the group matching who should reach it; that's the only authorization
  check for most routes (some controllers also self-check via `abort_unless($request->user()->role === ...)`
  for finer-grained cases inside a shared group).
- Controllers stay thin and delegate business logic to constructor-injected classes in
  `app/Services/` (`AttendanceService`, `LeaveService`, `JadwalService`, `NotificationService`,
  etc.). Controllers call `Inertia::render('admin/guru-page', [...])` for page loads (props can be
  lazy closures) or return `response()->json(...)` for XHR-style endpoints (e.g. QR scan results,
  unread counts). On the frontend, those JSON endpoints are called through the `api` wrapper in
  `resources/js/lib/api.ts` (thin axios client, throws `ApiError` with the server's message) —
  page-load data instead arrives as Inertia props, never fetched client-side.
- **Error convention**: services/controllers call `abort($status, $message)` (403/404/409/...)
  directly rather than returning error responses. `bootstrap/app.php` intercepts `HttpException`
  for Inertia requests and converts it to `back()->withErrors(['message' => $e->getMessage()])`,
  so the message lands as a normal Inertia form error the page can render — don't build a separate
  error-response mechanism.

### Frontend: page resolution & layout

- `resources/js/main.tsx` eager-globs `./pages/**/*.tsx` and resolves Inertia's `Inertia::render('admin/guru-page', ...)`
  to `resources/js/pages/admin/guru-page.tsx` — the string passed to `Inertia::render` must match
  the page file path (minus extension) under `pages/`.
- Every page is auto-wrapped in a layout by its name: pages under `sppd/` get `SppdLayout` (SPPD sidebar), all others get
  `DashboardLayout` (`resources/js/layouts/dashboard-layout.tsx`, absensi sidebar) — *except* the ones listed in
  `BARE_PAGES` in `main.tsx` (currently `login-page`, `kiosk-page`, `module-picker-page`). Add a new standalone/full-bleed
  page to `BARE_PAGES` rather than fighting the layout inside the page component. A screen that must appear in both
  modules needs a route and page under each prefix, otherwise the sidebar switches modules: the profile page is
  `/profil` (`admin/profil-page`, `guru/profil-page`) for absensi and `/sppd/profil` (`sppd/profil`, reusing the same
  components) for SPPD. Both sidebars carry *Profil* in the "Sistem" group and *Keluar* in the footer.
- Path alias `@/` → `resources/js/` (see `vite.config.ts` / `tsconfig.json`). UI primitives are
  shadcn/ui (`components.json`, style `base-nova`, base color `neutral`) under
  `resources/js/components/ui`; add new ones with the `shadcn` CLI rather than hand-rolling (the CLI prompts to overwrite
  existing files and may add stray dependencies to `package.json`; check `git diff` afterwards).
- **Never use `window.confirm`/`alert`/`prompt`.** Confirmations use the shadcn `AlertDialog` through the `useConfirm()` hook
  (`resources/js/hooks/use-confirm.tsx`): `const { confirm, confirmDialog } = useConfirm()`, render `{confirmDialog}` once in
  the component and `if (!(await confirm({ title, description, destructive }))) return` in the handler.
- `HandleInertiaRequests::share()` puts the authenticated user (`id`, `username`, `role`, and
  nested `guru` profile) and a one-shot `flash.toast` into every page's props — read auth state
  from Inertia props / `resources/js/context/auth-context.tsx`, not a separate fetch.

### Attendance domain logic

- `App\Services\AttendanceService` implements the check-in/check-out state machine
  (`recordAttendance` in that file has the authoritative comments): first scan/check-in of the day
  creates the `attendance` row and sets `statusMasuk` (`HADIR` vs `TELAT`, based on that day's
  `JadwalHari.batasTelat`); second one fills `jamPulang`. Both check-in paths funnel into this one
  private method — they only differ in how presence is verified:
  - **Kiosk/QR path** (`checkinByQrToken`): a shared school device (logged in as `ADMIN`) scans a
    guru's permanent static `qrToken` (`resources/js/pages/kiosk-page.tsx`). Two input modes, switched by a
    button on the page and remembered per device in `localStorage` (`kiosk-input-mode`): **kamera**
    (`html5-qrcode` decoding in-browser, default) and **alat** (USB barcode/QR scanner that types the token
    plus Enter into an always-focused input, with a clock/card layout). Both modes call the same
    `handleScan` → `POST /kiosk/scan-qr`, so the server logic below is identical for either. Physical
    presence at the device *is* the proof; who's logged into the browser is irrelevant.
  - **Web path** (`checkinWeb`): the guru's own browser reports GPS, checked against
    `App\Support\Geofence` (`SCHOOL_LAT`/`SCHOOL_LNG`/`SCHOOL_RADIUS_METERS`, currently 100m).
    **School coordinates are hardcoded constants in `Geofence.php`**, not a DB/settings value —
    despite `Settings` existing, it only stores `namaSekolah`. To relocate the school, edit that
    file.
  - Per-day schedule (open time, late cutoff, checkout time, briefing window, active/inactive) is
    `JadwalHari`, one row per weekday, served through `JadwalService`.
- Briefing attendance (`BriefingAttendance` / `BriefingController` / `BriefingService`) is a
  parallel, separate record for the morning briefing session — don't conflate it with regular
  `Attendance`. It is written from the kiosk scan: the first scan of the day during the briefing window
  records MASUK and briefing together (`catatDariScanKiosk`); a scan from a guru who already checked in
  (e.g. via web/GPS) records BRIEFING only; a scan before the window records MASUK only. The
  `/briefing` admin page (`pages/admin/briefing-page.tsx`) no longer has its own camera scanner: it is
  only the recap table, filters, Excel export and manual correction (`/briefing/manual`). The
  `/briefing/scan` endpoint still exists but no page calls it.
- Recap/export (`RekapController`, `App\Support\XlsxExport`, PhpSpreadsheet) reconstructs a full
  date range per guru from `Attendance` + approved `LeaveRequest` + `Holiday`, filling in `ALPA`
  for unresolved past school days — see `AttendanceService::rekap()`.

### Seeding

Two independent, non-composable seeders — never run both against the same DB:

- `php artisan db:seed` (`DatabaseSeeder`) — fake dev roster (1 admin, 1 kepsek, ~23 sample guru),
  fixed dummy passwords, no attendance data.
- `php artisan db:seed --class=ProductionSyncSeeder` — truncates and reloads from a real
  phpMyAdmin dump at `database/seeders/data/production.sql` (gitignored), for developing against
  actual production data. Safe to re-run. See the class docblock before changing table order or
  columns — it patches in a couple of columns added after the dump was taken.

### Environment gotchas

- `INERTIA_ENCRYPT_HISTORY` must stay `true` in every environment — it's what stops the browser
  back button from showing a stale login form or another account's dashboard from Inertia's
  history cache after login/logout (see `LoginController`).
- On Windows, web push (`minishlink/web-push`, VAPID) fails silently without `OPENSSL_CONF`
  pointed at a real `openssl.cnf` (e.g. Git for Windows' copy) — see the comment in `.env.example`.
- Never commit `.env*` (other than `.env.example`) or any database dump — `database/seeders/data/production.sql`
  and `*.sqlite` are gitignored; keep `.env.example` in sync when the env shape changes.
- Tests run against a real MySQL database, not sqlite `:memory:` — several migrations use raw
  MySQL DDL (`NOW(3)`, `UUID()`, `ALTER COLUMN ... SET DEFAULT`) that sqlite can't execute. Create
  the test DB once locally (`CREATE DATABASE absensi_testing;` via the same root/no-password
  connection as `.env.local`); `phpunit.xml` points at it. `tests/TestCase.php` applies
  `RefreshDatabase` and adds `actingAsAdmin()`/`actingAsGuru()`/`actingAsKepsek()` plus
  `freezeAt()`/`setJadwalHari()` helpers for deterministic attendance-window tests — use those
  instead of hand-rolling auth/time setup in new tests.

## Shared database (SPPD)

SPPD (surat perintah perjalanan dinas) adalah modul di repo ini, bukan aplikasi terpisah: `App\Http\Controllers\Sppd`,
rute `/sppd/*`, `resources/js/pages/sppd`, migrasi `*_sppd_*`, dan layanan di `App\Services\Sppd`. Dulu SPPD proyek
Laravel sendiri (`../sppd`) yang digabung ke basis data ini pada 2026-09-23 lalu foldernya dihapus; **semua perubahan skema
SPPD ditulis dan dimigrasi dari repo ini**. Basis data `absensi_laravel` berisi data nyata kedua bagian, jadi jangan
menjalankan `migrate:fresh`, `migrate:refresh`, atau `db:wipe`.

- **`users` adalah satu-satunya login** untuk absensi dan SPPD. Kolom profil SPPD (`name`, `jabatan`, `signature_path`,
  `is_active`) ada di tabel ini bersama kolom absensi (`username`, `password`, `role`). `role` (`ADMIN`/`GURU`/`KEPSEK`)
  boleh null: staf yang hanya memakai SPPD (TU, bendahara non-guru) tidak punya peran absensi. Otorisasi SPPD ada di
  atasnya lewat `spatie/laravel-permission` (middleware `sppd.role:`, enum `App\Enums\Sppd\RoleName`: `pemohon`,
  `kepala_sekolah`, `tu`, `bendahara`), sehingga satu orang bisa punya `role` absensi dan peran SPPD sekaligus (mis.
  `KEPSEK` di absensi dan `kepala_sekolah` di SPPD). Mengubah kolom atau peran di `users` berdampak ke absensi, SPPD, dan
  login web, jadi periksa kedua sisi.
- **Tabel domain SPPD** (`pengajuan_sppds`, `sppds`, `pencairans`, `log_audits`, `pengajuan_pengikut`,
  `laporan_perjalanans`, `laporan_perjalanan_fotos`, `pengaturans`) tanpa awalan. Setiap kolom yang merujuk pengguna (`pemohon_id`,
  `disetujui_oleh`, `diterbitkan_oleh`, `dicairkan_oleh`, `user_id`, `ditulis_oleh`) bertipe `string(191)` agar cocok
  dengan `users.id` (ULID), bukan `foreignId` bawaan Laravel.
- **Tabel berawalan `sppd_`** ada karena namanya sudah dipakai tabel absensi yang bentuknya berbeda
  (`sppd_notifications` vs `notifications` absensi), atau karena SPPD punya penyimpanan sesi/cache/antrean sendiri
  (`sppd_sessions`, `sppd_cache`/`sppd_cache_locks`, `sppd_jobs`/`sppd_job_batches`/`sppd_failed_jobs`). Absensi sendiri
  memakai `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.
- **Tidak ada TTE dan konfirmasi kedatangan lagi.** Tanda tangan elektronik (QR, sidik jari, halaman verifikasi publik) dan
  form konfirmasi kedatangan di tujuan dihapus; sisi belakang SPPD (pejabat tujuan, tanggal tiba dan pulang) diisi
  manual dengan pulpen oleh pejabat penerima. Gambar tanda tangan Kepala Sekolah di profil (`signature_path`, disalin ke
  `tanda_tangan_kepsek_path` saat menyetujui) masih dipakai di PDF. Migrasi `2026_10_02_100100_drop_sppd_tte_dan_konfirmasi_kedatangan`
  membuang kolom `tte_*` dan tabel `konfirmasi_kedatangans`; jalankan hanya bila data lamanya memang boleh hilang.
- **PDF SPPD** hanya satu template: `resources/views/pdf/sppd.blade.php` (A4 mendatar, satu halaman depan-belakang), data
  disiapkan `App\Services\Sppd\SppdPdfData`, diunduh lewat `PengajuanSppdController::downloadSppd`. Template aktual,
  portrait, dan halaman pengaturan template sudah dihapus. Sisi belakang (pejabat tujuan, tanggal tiba/pulang) sengaja kosong.
- **Berkas unggahan** (foto guru, lampiran izin, undangan, tanda tangan, foto laporan perjalanan) disajikan lewat
  `GET /uploads/{path}` (`UploadController`) dan hanya untuk pengguna yang login (middleware `auth`).
- **Laporan perjalanan** (`LaporanPerjalanan` + `LaporanFoto`, tabel `laporan_perjalanans` dan `laporan_perjalanan_fotos`): pemohon
  mengisi rangkuman dan 1 sampai 10 foto dokumentasi selama status `sedang_ditugaskan` (policy `isiLaporan`, rute
  `POST /sppd/pengajuan/{id}/laporan`). Hanya tampil di halaman pengajuan, tidak masuk PDF SPPD. Berkas disimpan di disk
  `public` folder `laporan-perjalanan`.
