<?php

use App\Http\Controllers\Pguru\AuthController;
use App\Http\Controllers\Pguru\ExportNilaiController;
use App\Http\Controllers\Pguru\ImportController;
use App\Http\Controllers\Pguru\KelasController;
use App\Http\Controllers\Pguru\NilaiController;
use App\Http\Controllers\Pguru\SiswaController;
use App\Http\Controllers\Pguru\SupervisiController;
use Illuminate\Support\Facades\Route;

/*
 * API aplikasi mobile SIMAK (Expo, dulu "Perangkat Guru"). Semua di bawah /api/pguru, autentikasi token Sanctum
 * lewat guard `pguru` yang memakai akun absensi (tabel `users`: username + password). Tidak ada pendaftaran,
 * verifikasi email, maupun kelola akun di sini: akun dibuat dan dinonaktifkan admin di absensi.
 */
Route::prefix('pguru')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

    Route::middleware(['auth:pguru', 'pguru.aktif'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('kelas', [KelasController::class, 'index']);
        Route::post('kelas', [KelasController::class, 'store']);
        Route::put('kelas/{id}', [KelasController::class, 'update']);
        Route::delete('kelas/{id}', [KelasController::class, 'destroy']);

        Route::get('kelas/{kelasId}/siswa', [SiswaController::class, 'index']);
        Route::post('kelas/{kelasId}/siswa', [SiswaController::class, 'store']);
        Route::put('siswa/{id}', [SiswaController::class, 'update']);
        Route::delete('siswa/{id}', [SiswaController::class, 'destroy']);

        Route::put('kelas/{kelasId}/nilai', [NilaiController::class, 'update']);

        Route::post('import/siswa', [ImportController::class, 'siswa'])->middleware('throttle:10,1');

        Route::get('nilai/export', [ExportNilaiController::class, 'nilai']);
        Route::get('nilai/format-kosong', [ExportNilaiController::class, 'templateKosong']);

        Route::get('supervisi/instrumen', [SupervisiController::class, 'instrumen']);
        Route::get('supervisi', [SupervisiController::class, 'index']);
        Route::post('supervisi', [SupervisiController::class, 'store']);
        Route::get('supervisi/{id}', [SupervisiController::class, 'show']);
        Route::put('supervisi/{id}', [SupervisiController::class, 'update']);
        Route::delete('supervisi/{id}', [SupervisiController::class, 'destroy']);
        Route::get('supervisi/{id}/export', [SupervisiController::class, 'export']);
    });
});
