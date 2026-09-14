<?php

use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\JadwalController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guru\QrController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(Auth::check() ? '/dashboard' : '/login');
});

Route::get('/uploads/{path}', [UploadController::class, 'show'])->where('path', '.*');

Route::get('/login', [LoginController::class, 'create'])->name('login')->middleware('guest');

// Deliberately NOT behind 'guest' - if it were, RedirectIfAuthenticated would
// intercept and redirect to /dashboard for anyone with a still-valid session
// (e.g. a stale cached login form reached via the back button) *before*
// LoginController@store ever runs, silently discarding whatever credentials
// were submitted and leaving the OLD account logged in. Submitting valid
// credentials here must always switch the session to that account, logged
// in or not - store() already handles this safely via Auth::login() +
// session()->regenerate().
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

// Every role lands here - DashboardController picks the right content,
// mirroring js/frontend's unified "/dashboard" (DashboardHome component).
Route::middleware(['auth', 'role:ADMIN,GURU,KEPSEK'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe']);
});
Route::get('/push/vapid-public-key', [PushController::class, 'vapidPublicKey']);

// ADMIN-only: the kiosk scan station - never Kepsek, so the principal is
// never the one tied to the physical scan machine.
Route::middleware(['auth', 'role:ADMIN'])->group(function () {
    Route::get('/kiosk', [KioskController::class, 'index']);
    Route::post('/kiosk/scan-qr', [KioskController::class, 'scan']);

    Route::get('/briefing', [BriefingController::class, 'index']);
    Route::post('/briefing/scan', [BriefingController::class, 'scan']);
    Route::get('/briefing/rekap', [BriefingController::class, 'rekap']);
    Route::get('/briefing/rekap/export', [BriefingController::class, 'export']);
});

// ADMIN + KEPSEK: oversight features - data guru (read-only for Kepsek,
// enforced inside GuruController itself), rekap, approve izin/sakit, hari
// libur, notifikasi, pengaturan/jadwal.
Route::middleware(['auth', 'role:ADMIN,KEPSEK'])->group(function () {
    Route::get('/data-guru', [GuruController::class, 'index']);
    Route::post('/data-guru', [GuruController::class, 'store']);
    Route::get('/data-guru/{guru}/detail', [GuruController::class, 'detail']);
    Route::patch('/data-guru/{guru}', [GuruController::class, 'update']);
    Route::delete('/data-guru/{guru}', [GuruController::class, 'destroy']);
    Route::post('/data-guru/{guru}/foto', [GuruController::class, 'uploadFoto']);
    Route::post('/data-guru/{guru}/qr/regenerate', [GuruController::class, 'regenerateQr']);

    Route::get('/rekap', [RekapController::class, 'index']);
    Route::get('/rekap/export', [RekapController::class, 'export']);
    Route::post('/rekap/manual', [RekapController::class, 'manualUpsert']);

    Route::get('/persetujuan', [LeaveController::class, 'adminIndex']);
    Route::patch('/persetujuan/{leaveRequest}/review', [LeaveController::class, 'review']);

    Route::get('/hari-libur', [HolidayController::class, 'index']);
    Route::post('/hari-libur', [HolidayController::class, 'store']);
    Route::delete('/hari-libur/{holiday}', [HolidayController::class, 'destroy']);

    Route::get('/notifikasi', [NotificationController::class, 'index']);
    Route::get('/notifikasi/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifikasi/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifikasi/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/pengaturan', [SettingsController::class, 'edit']);
    Route::patch('/pengaturan', [SettingsController::class, 'update']);
    Route::patch('/pengaturan/jadwal/{hari}', [JadwalController::class, 'update']);
});

// ADMIN + GURU + KEPSEK: own profile - password change works for every
// role, photo upload only actually usable by GURU/KEPSEK (enforced inside
// the controller itself; Admin's own page never shows that control).
Route::middleware(['auth', 'role:ADMIN,GURU,KEPSEK'])->group(function () {
    Route::get('/profil', [ProfileController::class, 'edit']);
    Route::post('/profil/foto', [ProfileController::class, 'uploadFoto']);
    Route::post('/profil/password', [ProfileController::class, 'changePassword']);
});

// GURU + KEPSEK: own attendance, own izin/sakit, own QR - Kepsek checks in
// exactly like any other guru.
Route::middleware(['auth', 'role:GURU,KEPSEK'])->group(function () {
    Route::get('/qr', [QrController::class, 'index']);
    Route::get('/riwayat', [AttendanceController::class, 'riwayat']);
    Route::get('/izin', [LeaveController::class, 'index']);
    Route::post('/izin', [LeaveController::class, 'store']);

    Route::get('/school-location', [AttendanceController::class, 'schoolLocation']);
    Route::post('/checkin-web', [AttendanceController::class, 'checkinWeb']);
    Route::get('/attendance/detail/me', [AttendanceController::class, 'detailMe']);
});
