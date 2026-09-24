# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**Absensi Guru** — a teacher attendance system for SMK Budi Mulia, Karawang (Indonesian vocational
high school). Teachers (`GURU`) check in/out via QR code scan or geofenced web check-in; admins
(`ADMIN`) and the headmaster (`KEPSEK`) monitor attendance recaps in real time, approve leave
requests, and manage schedules. Everything is in Indonesian (UI text, route names, validation
messages, DB column values like `HADIR`/`TELAT`/`IZIN`/`SAKIT`/`ALPA`).

Backend is Laravel; frontend is Inertia.js + React (TypeScript) — server-rendered routing with a
React SPA feel, no separate REST API layer for pages.

Note: the repo root also holds a top-level `LOGIN.md` left over from an earlier Node/NestJS+Prisma
prototype of this app — it references `backend/prisma/seed.ts`, which no longer exists in this
Laravel rewrite. Don't treat it as current; the real seed logic is
`database/seeders/DatabaseSeeder.php` (see below). (This project used to live nested in a
`laravel/` subfolder one level below the git root; it was flattened so the repo root and the
Laravel app root are now the same directory.)

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

### Data model conventions (non-standard for Laravel — apply everywhere)

Every model in `app/Models` deviates from Laravel defaults the same way:

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
- Every page is auto-wrapped in `DashboardLayout` (`resources/js/layouts/dashboard-layout.tsx`)
  *except* the ones listed in `BARE_PAGES` in `main.tsx` (currently `login-page`, `kiosk-page`).
  Add a new standalone/full-bleed page to `BARE_PAGES` rather than fighting the layout inside the
  page component.
- Path alias `@/` → `resources/js/` (see `vite.config.ts` / `tsconfig.json`). UI primitives are
  shadcn/ui (`components.json`, style `base-nova`, base color `neutral`) under
  `resources/js/components/ui`; add new ones with the `shadcn` CLI rather than hand-rolling.
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
    guru's permanent static `qrToken`. Physical presence at the device *is* the proof; who's logged
    into the browser is irrelevant.
  - **Web path** (`checkinWeb`): the guru's own browser reports GPS, checked against
    `App\Support\Geofence` (`SCHOOL_LAT`/`SCHOOL_LNG`/`SCHOOL_RADIUS_METERS`, currently 100m).
    **School coordinates are hardcoded constants in `Geofence.php`**, not a DB/settings value —
    despite `Settings` existing, it only stores `namaSekolah`. To relocate the school, edit that
    file.
  - Per-day schedule (open time, late cutoff, checkout time, briefing window, active/inactive) is
    `JadwalHari`, one row per weekday, served through `JadwalService`.
- Briefing attendance (`BriefingAttendance` / `BriefingController` / `BriefingService`) is a
  parallel, separate check-in flow for the morning briefing session — don't conflate it with
  regular `Attendance`.
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

This app's database (`absensi_laravel`) is also used by a second, separate Laravel app — `sppd`
(`../sppd`, Surat Perintah Perjalanan Dinas) — merged in 2026-09-23 so both apps run on one MySQL
database instead of two. **This app owns `users` and its own tables.** SPPD owns its own domain
tables and anything prefixed `sppd_` — new SPPD-only schema changes are written and migrated from
the sppd project directly (`php artisan migrate` there only ever applies migrations not yet
recorded, so it can't collide with or re-run this app's migrations). Changes to `users` itself
still belong here. Never run `migrate:fresh` or `migrate:refresh` from the sppd project — both
would drop every table in this shared database, not just SPPD's.

- **`users` is the single shared login for both apps.** SPPD's profile columns (`name`, `jabatan`,
  `signature_path`, `is_active`) live on this table alongside absensi's own (`username`,
  `password`, `role`). `role` (`ADMIN`/`GURU`/`KEPSEK`) is nullable — SPPD-only staff (TU,
  bendahara who aren't teachers) may have no absensi role at all. SPPD's own authorization is
  layered on top via `spatie/laravel-permission` (`roles`, `permissions`, `model_has_roles`,
  `model_has_permissions`, `role_has_permissions` tables) — a person can simultaneously have an
  absensi `role` and one or more SPPD spatie roles (e.g. `tetitresnawati` is `KEPSEK` here and
  `kepala_sekolah` in SPPD).
- **SPPD's domain tables** (`pengajuan_sppds`, `sppds`, `pencairans`, `log_audits`,
  `pengajuan_pengikut`, `konfirmasi_kedatangans`, `pengaturans`) live in this database unprefixed —
  they never collided with anything absensi already had. Every column that references a user
  (`pemohon_id`, `disetujui_oleh`, `diterbitkan_oleh`, `dicairkan_oleh`, `user_id`,
  `dikonfirmasi_oleh`) is `string(191)` to match `users.id` (ULID), not Laravel's default
  `foreignId`/`unsignedBigInteger`.
- **Tables prefixed `sppd_`** exist only because the unprefixed name was already taken by an
  absensi table with an incompatible shape, or because SPPD needs its own isolated
  session/cache/queue storage: `sppd_notifications` (absensi already has its own, differently
  shaped `notifications` table), `sppd_sessions`, `sppd_cache`/`sppd_cache_locks`, `sppd_jobs`/
  `sppd_job_batches`/`sppd_failed_jobs` (SPPD runs `SESSION_DRIVER`/`CACHE_STORE`/
  `QUEUE_CONNECTION=database`; absensi uses `file`/`sync` and doesn't have these tables at all).
- Adding a migration here that changes `users` means also mirroring it into SPPD's *local* copy of
  that table's migration (`sppd/database/migrations/0001_01_01_000000_create_users_table.php`,
  used only to build its SQLite test schema) and, if behavior is affected, into SPPD's own
  `App\Models\User`. `sppd_*` tables and SPPD's domain tables (`pengajuan_sppds`, `sppds`, etc.)
  are migrated from the sppd project itself now, not from here — see its CLAUDE.md.
- The migration that added the SPPD tables also ran a one-off data copy from the old standalone
  `sppd` database (31 users, their spatie roles, and all SPPD domain rows), remapping SPPD's old
  auto-increment user ids to absensi's ULIDs by matching `username`. That database is no longer
  used by either app but hasn't been dropped.
