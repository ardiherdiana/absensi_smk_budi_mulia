<?php

namespace App\Services;

use App\Models\BriefingAttendance;
use App\Models\Guru;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;

// Mirrors backend/src/modules/briefing/briefing.service.ts exactly - admin
// scans a guru/kepsek's static QR to record them at today's briefing,
// gated on the day's own jamBriefing the same way absen masuk/pulang are
// gated on their own jam.
class BriefingService
{
    public function __construct(private JadwalService $jadwal) {}

    /** Today's briefing time, or null if today isn't a school day or has no
     * briefing scheduled - backs the admin scan page's empty state so the
     * camera only turns on once there's actually something to scan for. */
    public function jamBriefingHariIni(): ?string
    {
        $jadwalHari = $this->jadwal->getJadwalForDate(Carbon::today());

        return $jadwalHari->aktif ? $jadwalHari->jamBriefing : null;
    }

    /** Today's briefing closing time, or null if there's no closing gate
     * configured (or no briefing today at all) - null here means the scan
     * window simply never closes, matching this feature's original
     * behavior before this field existed. */
    public function jamSelesaiBriefingHariIni(): ?string
    {
        $jadwalHari = $this->jadwal->getJadwalForDate(Carbon::today());

        return $jadwalHari->aktif && $jadwalHari->jamBriefing ? $jadwalHari->jamSelesaiBriefing : null;
    }

    /** @return array{nama: string, fotoUrl: ?string, waktu: Carbon} */
    public function checkinBriefing(string $qrToken): array
    {
        $guru = Guru::where('qrToken', $qrToken)->first();
        if (! $guru) {
            abort(404, 'QR tidak dikenali');
        }
        if (! $guru->aktif) {
            abort(403, 'Akun guru sudah dinonaktifkan');
        }

        $tanggal = Carbon::today();
        $jadwalHari = $this->jadwal->getJadwalForDate($tanggal);
        if (! $jadwalHari->aktif) {
            abort(409, 'Hari ini bukan hari aktif sekolah sesuai jadwal');
        }
        if (! $jadwalHari->jamBriefing) {
            abort(409, 'Tidak ada jadwal briefing hari ini');
        }

        $now = Carbon::now();
        $gate = Carbon::parse($tanggal->toDateString().' '.$jadwalHari->jamBriefing);
        if ($now->lt($gate)) {
            abort(409, "Absen briefing belum dibuka (mulai {$jadwalHari->jamBriefing})");
        }

        if ($jadwalHari->jamSelesaiBriefing) {
            $closeGate = Carbon::parse($tanggal->toDateString().' '.$jadwalHari->jamSelesaiBriefing);
            if ($now->gt($closeGate)) {
                abort(409, "Absen briefing sudah ditutup (berakhir jam {$jadwalHari->jamSelesaiBriefing})");
            }
        }

        $existing = BriefingAttendance::where('guruId', $guru->id)->whereDate('tanggal', $tanggal)->first();
        if ($existing) {
            abort(409, "{$guru->nama} sudah absen briefing hari ini");
        }

        $record = BriefingAttendance::create([
            'guruId' => $guru->id,
            'tanggal' => $tanggal,
            'waktu' => $now,
        ]);

        return ['nama' => $guru->nama, 'fotoUrl' => $guru->fotoUrl, 'waktu' => $record->waktu];
    }

    /** @return array<int, array{guruId: string, nama: string, tanggal: string, waktu: ?string, status: 'HADIR'|'ALPA'|null}> */
    public function rekapBriefing(Carbon $from, Carbon $to, ?string $guruId = null): array
    {
        $guruQuery = Guru::query()->orderBy('nama');
        if ($guruId) {
            $guruQuery->where('id', $guruId);
        }
        $guruList = $guruQuery->get(['id', 'nama']);

        $records = BriefingAttendance::query()
            ->when($guruId, fn ($q) => $q->where('guruId', $guruId))
            ->whereDate('tanggal', '>=', $from->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->get();

        $leaves = LeaveRequest::query()
            ->when($guruId, fn ($q) => $q->where('guruId', $guruId))
            ->where('status', 'APPROVED')
            ->whereDate('tanggalMulai', '<=', $to->toDateString())
            ->whereDate('tanggalSelesai', '>=', $from->toDateString())
            ->get();

        $holidaySet = Holiday::whereDate('tanggal', '>=', $from->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->get()
            ->map(fn ($h) => $h->tanggal->toDateString())
            ->flip();

        $jadwalMap = $this->jadwal->getJadwalMap();

        $recordMap = $records->keyBy(fn ($r) => $r->guruId.'_'.$r->tanggal->toDateString());
        $today = Carbon::today();
        $rows = [];

        foreach ($guruList as $guru) {
            foreach ($this->eachDate($from, $to) as $date) {
                $jadwalHari = $jadwalMap->get($date->dayOfWeek);
                $adaBriefing = ($jadwalHari->aktif ?? false) && ! empty($jadwalHari?->jamBriefing) && ! $holidaySet->has($date->toDateString());

                if (! $adaBriefing) {
                    $rows[] = ['guruId' => $guru->id, 'nama' => $guru->nama, 'tanggal' => $date->toDateString(), 'waktu' => null, 'status' => null];

                    continue;
                }

                $record = $recordMap->get($guru->id.'_'.$date->toDateString());
                if ($record) {
                    $rows[] = [
                        'guruId' => $guru->id,
                        'nama' => $guru->nama,
                        'tanggal' => $date->toDateString(),
                        'waktu' => $record->waktu->toIso8601String(),
                        'status' => 'HADIR',
                    ];

                    continue;
                }

                $onLeave = $leaves->contains(
                    fn ($l) => $l->guruId === $guru->id
                        && $l->tanggalMulai->toDateString() <= $date->toDateString()
                        && $l->tanggalSelesai->toDateString() >= $date->toDateString()
                );

                $rows[] = [
                    'guruId' => $guru->id,
                    'nama' => $guru->nama,
                    'tanggal' => $date->toDateString(),
                    'waktu' => null,
                    'status' => $onLeave || $date->gt($today) ? null : 'ALPA',
                ];
            }
        }

        return $rows;
    }

    /** @return Carbon[] */
    private function eachDate(Carbon $from, Carbon $to): array
    {
        $dates = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }
}
