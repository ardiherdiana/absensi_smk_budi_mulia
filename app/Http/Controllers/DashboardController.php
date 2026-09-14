<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\GuruService;
use App\Services\LeaveService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

// Mirrors js/frontend's DashboardHome - ADMIN gets the oversight dashboard,
// GURU/KEPSEK both get the same self-service dashboard.
class DashboardController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private GuruService $guruService,
        private LeaveService $leave,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        if ($request->user()->role === 'ADMIN') {
            return $this->admin();
        }

        return $this->guru($request);
    }

    private function admin()
    {
        $today = Carbon::today();
        $trendDays = 30;
        $trendRows = $this->attendance->rekap($today->copy()->subDays($trendDays - 1), $today);

        return Inertia::render('admin/dashboard-page', [
            'guruList' => $this->guruService->list(),
            'today' => $this->attendance->rekap($today, $today),
            'pendingIzin' => $this->leave->list('PENDING'),
            'notifications' => $this->notifications->list(),
            'trend' => $this->aggregateTrend($trendRows),
        ]);
    }

    /** @return array<int, array{tanggal: string, hadir: int, telat: int, alpa: int, rate: float}> */
    private function aggregateTrend(array $rows): array
    {
        $byDate = [];
        foreach ($rows as $row) {
            if (! $row['status']) {
                continue;
            }
            $bucket = $byDate[$row['tanggal']] ?? ['hadir' => 0, 'telat' => 0, 'alpa' => 0, 'total' => 0];
            $bucket['total']++;
            if ($row['status'] === 'HADIR') {
                $bucket['hadir']++;
            } elseif ($row['status'] === 'TELAT') {
                $bucket['telat']++;
            } elseif ($row['status'] === 'ALPA') {
                $bucket['alpa']++;
            }
            $byDate[$row['tanggal']] = $bucket;
        }

        ksort($byDate);

        return collect($byDate)->map(fn ($b, $tanggal) => [
            'tanggal' => $tanggal,
            'hadir' => $b['hadir'],
            'telat' => $b['telat'],
            'alpa' => $b['alpa'],
            'rate' => $b['total'] === 0 ? 0 : ($b['hadir'] + $b['telat']) / $b['total'] * 100,
        ])->values()->all();
    }

    private function guru(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        $todayDate = Carbon::today();
        $todayAttendance = $this->attendance->myAttendance($guru->id, $todayDate, $todayDate)->first();

        return Inertia::render('guru/dashboard-page', [
            'today' => $todayAttendance,
            'detail' => $this->attendance->guruDetail($guru->id),
        ]);
    }
}
