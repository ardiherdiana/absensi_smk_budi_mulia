<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\JadwalService;
use Illuminate\Http\Request;

// Mirrors backend/src/modules/jadwal/jadwal.routes.ts. Stays a plain JSON
// endpoint (not an Inertia visit) - the page saves one day-column at a time
// and merges the response into that column's own local state.
class JadwalController extends Controller
{
    public function __construct(private JadwalService $jadwal) {}

    public function update(Request $request, int $hari)
    {
        abort_unless($hari >= 0 && $hari <= 6, 422, 'Hari tidak valid');

        $hhmm = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

        $validated = $request->validate([
            'aktif' => ['sometimes', 'boolean'],
            'jamMulaiAbsen' => ['sometimes', $hhmm],
            'jamMasuk' => ['sometimes', $hhmm],
            'batasTelat' => ['sometimes', $hhmm],
            'jamPulang' => ['sometimes', $hhmm],
            'jamBriefing' => ['sometimes', 'nullable', $hhmm],
            'jamSelesaiBriefing' => ['sometimes', 'nullable', $hhmm],
        ], [
            'jamMulaiAbsen.regex' => 'Format harus HH:mm',
            'jamMasuk.regex' => 'Format harus HH:mm',
            'batasTelat.regex' => 'Format harus HH:mm',
            'jamPulang.regex' => 'Format harus HH:mm',
            'jamBriefing.regex' => 'Format harus HH:mm',
            'jamSelesaiBriefing.regex' => 'Format harus HH:mm',
        ]);

        return response()->json($this->jadwal->updateJadwal($hari, $validated));
    }
}
