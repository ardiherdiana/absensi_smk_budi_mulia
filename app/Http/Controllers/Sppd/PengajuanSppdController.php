<?php

namespace App\Http\Controllers\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sppd\SimpanLaporanRequest;
use App\Http\Requests\Sppd\StorePengajuanRequest;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Services\Sppd\PengajuanWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PengajuanSppdController extends Controller
{
    public function __construct(private readonly PengajuanWorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = PengajuanSppd::query()->with('pemohon');

        if (! $user->isAdmin() && ! $user->hasAnyRole([RoleName::KepalaSekolah->value, RoleName::Tu->value, RoleName::Bendahara->value])) {
            $query->where('pemohon_id', $user->id);
        }

        $status = PengajuanStatus::tryFrom($request->string('status')->toString())?->value;

        if ($status) {
            $query->where('status', $status);
        }

        $pengajuans = $query->latest()->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('sppd/pengajuan/index', [
            'pengajuans' => $pengajuans,
            'filters' => ['status' => $status],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PengajuanSppd::class);

        return Inertia::render('sppd/pengajuan/create', [
            'pegawai' => User::query()
                ->pegawaiAktif()
                ->whereKeyNot(auth()->id())
                ->orderBy('name')
                ->get(['id', 'name', 'jabatan']),
        ]);
    }

    public function store(StorePengajuanRequest $request): RedirectResponse
    {
        $path = $request->file('undangan')?->store('undangan', 'public');

        $pengajuan = PengajuanSppd::create([
            'pemohon_id' => $request->user()->id,
            'tujuan' => $request->input('tujuan'),
            'maksud' => $request->input('maksud'),
            'alat_angkutan' => $request->input('alat_angkutan'),
            'keterangan' => $request->input('keterangan'),
            'tanggal_berangkat' => $request->date('tanggal_berangkat'),
            'jam_berangkat' => $request->input('jam_berangkat'),
            'tanggal_kembali' => $request->date('tanggal_kembali'),
            'jam_kembali' => $request->input('jam_kembali'),
            'undangan_path' => $path,
            'status' => PengajuanStatus::Draft,
        ]);

        $pengajuan->pengikuts()->sync($request->input('pengikut_ids', []));

        $this->workflow->ajukan($pengajuan, $request->user());

        return to_route('sppd.pengajuan.show', $pengajuan)->with('success', 'Pengajuan SPPD berhasil diajukan.');
    }

    public function show(Request $request, PengajuanSppd $pengajuan): Response
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load([
            'pemohon', 'penyetuju', 'sppd', 'pengikuts:users.id,users.name,users.jabatan',
            'pencairans.pencair', 'laporan.fotos', 'laporan.penulis:users.id,users.name',
        ]);

        $riwayat = LogAudit::query()
            ->where('entitas_terkait', PengajuanSppd::class)
            ->where('entitas_id', $pengajuan->id)
            ->with('user')
            ->oldest()
            ->get();

        $laporan = $pengajuan->laporan;

        return Inertia::render('sppd/pengajuan/show', [
            'pengajuan' => $pengajuan->makeHidden('laporan'),
            'riwayat' => $riwayat,
            'laporan' => $laporan ? [
                'ringkasan' => $laporan->ringkasan,
                'penulis' => $laporan->penulis?->name,
                'diperbarui_at' => $laporan->updated_at->toIso8601String(),
                'fotos' => $laporan->fotos->map(fn ($foto) => [
                    'id' => $foto->id,
                    'path' => $foto->path,
                    'nama_asli' => $foto->nama_asli,
                ])->all(),
            ] : null,
            'can' => [
                'approve' => $request->user()->can('approve', $pengajuan),
                'terbitkanSppd' => $request->user()->can('terbitkanSppd', $pengajuan),
                'isiLaporan' => $request->user()->can('isiLaporan', $pengajuan),
                'cairkanUangMuka' => $request->user()->can('cairkanUangMuka', $pengajuan),
                'selesaikan' => $request->user()->can('selesaikan', $pengajuan),
            ],
        ]);
    }

    public function approve(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('approve', $pengajuan);

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->workflow->setujui($pengajuan, $request->user(), $data['catatan'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan disetujui dan ditandatangani.');
    }

    public function reject(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('approve', $pengajuan);

        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']]);

        $this->workflow->tolak($pengajuan, $request->user(), $data['catatan']);

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function terbitkanSppd(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('terbitkanSppd', $pengajuan);

        $data = $request->validate(['akun_anggaran' => ['nullable', 'string', 'max:100']]);

        $this->workflow->terbitkanSppd($pengajuan, $request->user(), $data['akun_anggaran'] ?? null);

        return back()->with('success', 'SPPD berhasil diterbitkan.');
    }

    public function simpanLaporan(SimpanLaporanRequest $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $fotoBaru = collect($request->file('fotos', []))
            ->map(fn ($file) => [
                'path' => $file->store('laporan-perjalanan', 'public'),
                'nama_asli' => $file->getClientOriginalName(),
            ])
            ->all();

        $this->workflow->simpanLaporan(
            $pengajuan,
            $request->user(),
            $request->string('ringkasan')->toString(),
            $request->input('hapus_foto_ids', []),
            $fotoBaru,
        );

        return back()->with('success', 'Laporan perjalanan berhasil disimpan.');
    }

    public function cairkanUangMuka(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('cairkanUangMuka', $pengajuan);

        $data = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workflow->cairkanUangMuka($pengajuan, $request->user(), (float) $data['jumlah'], $data['keterangan'] ?? null);

        return back()->with('success', 'Uang muka berhasil dicairkan.');
    }

    public function selesaikan(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('selesaikan', $pengajuan);

        $this->workflow->selesaikan($pengajuan, $request->user());

        return back()->with('success', 'Perjalanan dinas ditandai selesai & diarsipkan.');
    }

    public function downloadSppd(PengajuanSppd $pengajuan)
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);
        abort_if(! $pengajuan->sppd, 404);

        $nomor = str_replace('/', '-', $pengajuan->sppd->nomor_sppd);

        return Pdf::loadView('pdf.sppd', ['pengajuan' => $pengajuan])
            ->setPaper('a4', 'landscape')
            ->download("sppd-{$nomor}.pdf");
    }
}
