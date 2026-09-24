<?php

use App\Enums\Sppd\RoleName as SppdRoleName;
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
use App\Http\Controllers\Sppd\AuditLogController as SppdAuditLogController;
use App\Http\Controllers\Sppd\DashboardController as SppdDashboardController;
use App\Http\Controllers\Sppd\LaporanController as SppdLaporanController;
use App\Http\Controllers\Sppd\NotificationController as SppdNotificationController;
use App\Http\Controllers\Sppd\PegawaiController as SppdPegawaiController;
use App\Http\Controllers\Sppd\PengajuanSppdController;
use App\Http\Controllers\Sppd\SignatureController as SppdSignatureController;
use App\Http\Controllers\Sppd\TemplateSppdController;
use App\Http\Controllers\Sppd\VerifikasiTteController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect(Auth::check() ? '/menu-utama' : '/login');
});

Route::get('/uploads/{path}', [UploadController::class, 'show'])->where('path', '.*');

Route::get('/login', [LoginController::class, 'create'])->name('login')->middleware('guest');

Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::middleware(['auth', 'role:ADMIN,GURU,KEPSEK'])->group(function () {
    // Nama route tetap 'dashboard' (bukan 'menu-utama') karena middleware `guest` bawaan
    // Laravel (Illuminate\Auth\Middleware\RedirectIfAuthenticated) mencari route bernama
    // persis 'dashboard' untuk mengalihkan pengguna yang sudah login dari /login - lihat
    // defaultRedirectUri() di vendor. URI-nya sendiri sudah /menu-utama.
    Route::get('/menu-utama', fn () => Inertia::render('module-picker-page'))->name('dashboard');
    Route::get('/absen/dashboard', [DashboardController::class, 'index']);

    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe']);
});
Route::get('/push/vapid-public-key', [PushController::class, 'vapidPublicKey']);

Route::middleware(['auth', 'role:ADMIN'])->group(function () {
    Route::get('/kiosk', [KioskController::class, 'index']);
    Route::post('/kiosk/scan-qr', [KioskController::class, 'scan']);

    Route::get('/briefing', [BriefingController::class, 'index']);
    Route::post('/briefing/scan', [BriefingController::class, 'scan']);
    Route::post('/briefing/manual', [BriefingController::class, 'manualUpsert']);
    Route::get('/briefing/rekap', [BriefingController::class, 'rekap']);
    Route::get('/briefing/rekap/export', [BriefingController::class, 'export']);
});

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

Route::middleware(['auth', 'role:ADMIN,GURU,KEPSEK'])->group(function () {
    Route::get('/profil', [ProfileController::class, 'edit']);
    Route::post('/profil/foto', [ProfileController::class, 'uploadFoto']);
    Route::post('/profil/password', [ProfileController::class, 'changePassword']);
});

Route::middleware(['auth', 'role:GURU,KEPSEK'])->group(function () {
    Route::get('/qr', [QrController::class, 'index']);
    Route::get('/riwayat', [AttendanceController::class, 'riwayat']);
    Route::get('/izin', [LeaveController::class, 'index']);
    Route::post('/izin', [LeaveController::class, 'store']);

    Route::get('/school-location', [AttendanceController::class, 'schoolLocation']);
    Route::post('/checkin-web', [AttendanceController::class, 'checkinWeb']);
    Route::get('/attendance/detail/me', [AttendanceController::class, 'detailMe']);
});

// Modul SPPD — Surat Perintah Perjalanan Dinas. Otorisasi role via spatie/laravel-permission
// (pemohon, kepala_sekolah, tu, bendahara), lapis di atas role absensi (ADMIN/GURU/KEPSEK).
// Akun ADMIN lolos semua gate di bawah ini (lihat App\Models\User::isAdmin()).

// Verifikasi TTE — publik (dipindai dari QR pada SPPD), dibatasi laju untuk mencegah penebakan kode.
Route::get('/sppd/verifikasi/{kode}', [VerifikasiTteController::class, 'show'])
    ->where('kode', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1')
    ->name('sppd.tte.verifikasi');

Route::prefix('sppd')->name('sppd.')->middleware('auth')->group(function () {
    Route::get('/dashboard', SppdDashboardController::class)->name('dashboard');

    Route::get('/notifikasi', [SppdNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifikasi/read-all', [SppdNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifikasi/{notification}/read', [SppdNotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/audit-log', [SppdAuditLogController::class, 'index'])->name('audit-log.index');

    // Pengajuan SPPD — dapat diakses semua role terautentikasi, otorisasi detail via Policy.
    Route::get('/pengajuan', [PengajuanSppdController::class, 'index'])->name('pengajuan.index');
    Route::get('/pengajuan/{pengajuan}', [PengajuanSppdController::class, 'show'])->name('pengajuan.show');
    Route::get('/pengajuan/{pengajuan}/sppd.pdf', [PengajuanSppdController::class, 'downloadSppd'])->name('pengajuan.sppd-pdf');

    // Pemohon
    Route::middleware('sppd.role:'.SppdRoleName::Pemohon->value)->group(function () {
        Route::get('/pengajuan-create', [PengajuanSppdController::class, 'create'])->name('pengajuan.create');
        Route::post('/pengajuan', [PengajuanSppdController::class, 'store'])->name('pengajuan.store');
        Route::post('/pengajuan/{pengajuan}/kedatangan', [PengajuanSppdController::class, 'konfirmasiKedatangan'])->name('pengajuan.kedatangan');
    });

    // Kepala Sekolah
    Route::middleware('sppd.role:'.SppdRoleName::KepalaSekolah->value)->group(function () {
        Route::post('/pengajuan/{pengajuan}/approve', [PengajuanSppdController::class, 'approve'])->name('pengajuan.approve');
        Route::post('/pengajuan/{pengajuan}/reject', [PengajuanSppdController::class, 'reject'])->name('pengajuan.reject');
        Route::get('/tanda-tangan-saya', [SppdSignatureController::class, 'edit'])->name('signature.edit');
        Route::post('/tanda-tangan-saya', [SppdSignatureController::class, 'update'])->name('signature.update');
    });

    // TU (Tata Usaha)
    Route::middleware('sppd.role:'.SppdRoleName::Tu->value)->group(function () {
        Route::post('/pengajuan/{pengajuan}/terbitkan-sppd', [PengajuanSppdController::class, 'terbitkanSppd'])->name('pengajuan.terbitkan-sppd');
        Route::post('/pengajuan/{pengajuan}/selesai', [PengajuanSppdController::class, 'selesaikan'])->name('pengajuan.selesai');

        Route::get('/pengaturan/template-sppd', [TemplateSppdController::class, 'edit'])->name('template-sppd.edit');
        Route::put('/pengaturan/template-sppd', [TemplateSppdController::class, 'update'])->name('template-sppd.update');
        Route::get('/pengaturan/template-sppd/pratinjau/{template}', [TemplateSppdController::class, 'pratinjau'])->name('template-sppd.pratinjau');

        Route::get('/pegawai', [SppdPegawaiController::class, 'index'])->name('pegawai.index');
        Route::get('/pegawai/create', [SppdPegawaiController::class, 'create'])->name('pegawai.create');
        Route::post('/pegawai', [SppdPegawaiController::class, 'store'])->name('pegawai.store');
        Route::get('/pegawai/{pegawai}/edit', [SppdPegawaiController::class, 'edit'])->name('pegawai.edit');
        Route::put('/pegawai/{pegawai}', [SppdPegawaiController::class, 'update'])->name('pegawai.update');
        Route::delete('/pegawai/{pegawai}', [SppdPegawaiController::class, 'destroy'])->name('pegawai.destroy');
    });

    // Bendahara
    Route::middleware('sppd.role:'.SppdRoleName::Bendahara->value)->group(function () {
        Route::post('/pengajuan/{pengajuan}/cairkan-uang-muka', [PengajuanSppdController::class, 'cairkanUangMuka'])->name('pengajuan.cairkan-uang-muka');

        Route::get('/laporan', [SppdLaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/export-excel', [SppdLaporanController::class, 'exportExcel'])->name('laporan.export-excel');
        Route::get('/laporan/export-pdf', [SppdLaporanController::class, 'exportPdf'])->name('laporan.export-pdf');
    });
});
