@php
    /*
     * Koordinat tata letak diambil dari format SPPD aktual (A4 landscape) dalam satuan piksel
     * gambar 2000x1414, lalu dikonversi ke pt (841,89pt / 2000) agar posisi persis sama.
     */
    $u = fn (float $px): string => round($px * 0.42094, 2).'pt';
    $tengah = fn (float $cy): string => $u($cy - 14.25);

    // Data tampilan dipakai bersama semua template (lihat SppdPdfData) agar isi dokumen selalu sama.
    extract(app(\App\Services\Sppd\SppdPdfData::class)->data($pengajuan));

    /** Perkecil font agar teks satu baris muat pada kolom (kolom form berukuran tetap). */
    $fitFont = fn (?string $teks, int $batas = 32, float $normal = 9.75): string => 'font-size: '
        .($teks !== null && mb_strlen($teks) > $batas ? round($normal * $batas / mb_strlen($teks), 2) : $normal).'pt;';

    // Baris tambahan pada tabel (maksud, pengikut > 1, keterangan > 1 baris) menggeser blok tanda tangan di bawahnya.
    $tambahBaris = max(0, $pengikuts->count() - 1)
        + max(0, ($keterangan ? (int) ceil(mb_strlen($keterangan) / 34) : 1) - 1)
        + max(0, ($pengajuan->maksud ? (int) ceil(mb_strlen($pengajuan->maksud) / 32) : 1) - 1);
    $geser = $tambahBaris * 29.3;
    $gy = fn (float $y): string => $u($y + $geser);
    $gc = fn (float $cy): string => $tengah($cy + $geser);

    $li = fn (string $huruf, ?string $teks, int $lebar = 16): string => '<table class="li"><tr>'
        .'<td style="width:'.$lebar.'pt">'.e($huruf).'</td>'
        .'<td>'.($teks === null || $teks === '' ? '&nbsp;' : e($teks)).'</td></tr></table>';

    /**
     * Blok "label : nilai" pada sisi belakang. Tiap blok: x, y, (romawi), lebar kolom romawi/label/titik dua/nilai, baris.
     * Bagian I dan II (persinggahan) tidak dicetak karena sistem hanya mencatat satu tujuan.
     */
    $pejabatTujuan = $kedatangan
        ? [['Kepala', $kedatangan->pejabat_nama], ['Jabatan', $kedatangan->pejabat_jabatan]]
        : [['Kepala', '']];

    $blok = [
        ['x' => 1474, 'y' => 65, 'romawi' => null, 'w' => [0, 158, 18, 290], 'baris' => [
            ['Berangkat dari', $sekolah], ['Ke', $tujuan], ['Pada Tanggal', $tanggalBerangkat],
        ]],
        ['x' => 1019, 'y' => 353, 'romawi' => null, 'w' => [0, 152, 15, 235], 'baris' => [
            ['Tiba di', $tujuan], ['Pada Tanggal', $tibaTanggal], ...$pejabatTujuan,
        ]],
        ['x' => 1474, 'y' => 353, 'romawi' => null, 'w' => [0, 158, 18, 290], 'baris' => [
            ['Berangkat dari', $tujuan], ['Ke', $sekolah], ['Pada Tanggal', $pulangTanggal], ...$pejabatTujuan,
        ]],
        ['x' => 1022, 'y' => 988, 'romawi' => 'III.', 'w' => [35, 190, 15, 300], 'baris' => [
            ['Tiba kembali di', 'Karawang'], ['Pada tanggal', $tanggalKembali],
        ]],
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPPD {{ $sppd->nomor_sppd }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: Helvetica, sans-serif; font-size: 9.75pt; color: #000; }
        .abs { position: absolute; }
        .t { position: absolute; white-space: nowrap; line-height: 12pt; }
        .garis { position: absolute; background: #000; }
        table { border-collapse: collapse; }
        table.kv { position: absolute; }
        table.kv td { padding: 0; line-height: 12pt; vertical-align: top; }
        table.sppd { position: absolute; }
        table.sppd td { border: 0.8pt solid #000; padding: 0 0 0 5pt; line-height: 11.8pt; vertical-align: top; }
        table.sppd td.no { text-align: center; padding: 0; }
        table.sppd td.mid { vertical-align: middle; }
        table.li { width: 100%; }
        table.sppd table.li td { border: 0; padding: 0; line-height: 12pt; }
        .nama { font-weight: bold; text-decoration: underline; }
        .pratinjau { position: fixed; bottom: 4pt; left: 0; right: 0; text-align: center; font-size: 7pt; color: #c00000; }
    </style>
</head>
<body>
    @if($pratinjau ?? false)
        <div class="pratinjau">PRATINJAU TEMPLATE &mdash; DATA CONTOH, BUKAN DOKUMEN RESMI</div>
    @endif

    {{-- Kop surat --}}
    <img class="abs" src="{{ public_path('logo_smk.png') }}" style="left: {{ $u(38) }}; top: {{ $u(32) }}; width: {{ $u(182) }};">
    <div class="t" style="left: {{ $u(236) }}; top: {{ $u(34) }}; font-size: 9.3pt; font-weight: bold;">YAYASAN AL-ULYA KARAWANG</div>
    <div class="t" style="left: {{ $u(236) }}; top: {{ $u(64) }}; font-size: 16pt; line-height: 20pt; font-weight: bold;">SMK BUDI MULIA</div>
    <div class="t" style="left: {{ $u(236) }}; top: {{ $u(112) }}; font-size: 9.2pt; line-height: 11.4pt; font-weight: bold;">Terakreditasi A, berdasarkan SK BAN-SM nomor 763/BAN-SM/SK/2019</div>
    <div class="t" style="left: {{ $u(236) }}; top: {{ $u(140) }}; font-size: 9.1pt; line-height: 11.4pt; font-weight: bold;">Jl. Ciherang Wadas, Kec. Telukjambe Timur Kab. Karawang 41361</div>
    <div class="t" style="left: {{ $u(236) }}; top: {{ $u(167) }}; font-family: 'Times-Roman', serif; font-size: 12pt; line-height: 11.4pt; font-weight: bold;">Telepon (0267) 8458459 e-Mail smk_budimulia@teachers.org</div>
    <div class="garis" style="left: {{ $u(28) }}; top: {{ $u(211) }}; width: {{ $u(976) }}; height: {{ $u(5) }};"></div>
    <div class="garis" style="left: {{ $u(28) }}; top: {{ $u(222) }}; width: {{ $u(976) }}; height: {{ $u(2) }};"></div>

    {{-- Lampiran / nomor / tanggal --}}
    <table class="kv" style="left: {{ $u(85) }}; top: {{ $tengah(251) }}; width: {{ $u(600) }};">
        <tr><td style="width: {{ $u(173) }};">Lampiran</td><td style="width: {{ $u(14) }};">:</td><td>Surat Perjalanan Dinas</td></tr>
        <tr><td>Nomor</td><td>:</td><td>{{ $sppd->nomor_sppd }}</td></tr>
        <tr><td>Tanggal</td><td>:</td><td>{{ $tanggalTerbit }}</td></tr>
    </table>

    <div class="t" style="left: {{ $u(85) }}; width: {{ $u(841) }}; top: {{ $tengah(363) }}; text-align: center; font-size: 10pt; font-weight: bold; text-decoration: underline;">SURAT PERJALANAN DINAS (SPPD)</div>

    {{-- Tabel isian (sisi depan) --}}
    <table class="sppd" style="left: {{ $u(85) }}; top: {{ $u(404) }}; width: {{ $u(892) }};">
        <tr>
            <td class="no" style="width: {{ $u(64) }};">1</td>
            <td style="width: {{ $u(423) }};">Pengguna Anggaran/kuasa Pengguna Anggaran</td>
            <td style="width: {{ $u(372) }};">{{ $namaPejabat }}</td>
        </tr>
        <tr>
            <td class="no">2</td>
            <td>Nama/NIP pegawai yang melaksanakan Perjalanan Dinas</td>
            <td class="mid">{{ $pemohon->name }}</td>
        </tr>
        <tr>
            <td class="no">3</td>
            <td>{!! $li('a.', 'Pangkat dan Golongan') !!}{!! $li('b.', 'Jabatan') !!}{!! $li('c.', 'Tingkat Biaya Perjalanan Dinas') !!}</td>
            <td>{!! $li('a.', '-') !!}{!! $li('b.', $pemohon->jabatan ?: '-') !!}{!! $li('c.', 'Disesuaikan') !!}</td>
        </tr>
        <tr>
            <td class="no">4</td>
            <td>Maksud Perjalanan Dinas</td>
            <td>{{ $pengajuan->maksud }}</td>
        </tr>
        <tr>
            <td class="no">5</td>
            <td>Alat angkutan yang digunakan</td>
            <td style="{{ $fitFont($alatAngkutan, 32) }}">{{ $alatAngkutan }}&nbsp;</td>
        </tr>
        <tr>
            <td class="no">6</td>
            <td>{!! $li('a.', 'Tempat Berangkat') !!}{!! $li('b.', 'Tempat tujuan') !!}</td>
            <td>{!! $li('a.', $sekolah, 11) !!}{!! $li('b.', $tujuan, 11) !!}</td>
        </tr>
        <tr>
            <td class="no">7</td>
            <td>{!! $li('a.', 'Lama berangkat') !!}{!! $li('b.', 'Tanggal berangkat') !!}{!! $li('c.', 'Tanggal harus kembali/tiba di tempat baru*)') !!}</td>
            <td>{!! $li('a.', $lamaHari.' Hari', 13) !!}{!! $li('b.', $tanggalBerangkat.', '.$jamBerangkat.' WIB', 13) !!}{!! $li('c.', $tanggalKembali.', '.$jamKembali.' WIB', 13) !!}</td>
        </tr>
        <tr>
            <td class="no">8</td>
            <td>Pengikut : Nama</td>
            <td>Jabatan</td>
        </tr>
        <tr>
            <td class="no">9</td>
            <td>
                @forelse ($pengikuts as $i => $pengikut)
                    <div style="white-space: nowrap; line-height: 11.8pt; {{ $fitFont($pengikut->name, 30) }}">{{ $i + 1 }}. {{ $pengikut->name }}</div>
                @empty
                    &nbsp;
                @endforelse
            </td>
            <td>
                @forelse ($pengikuts as $pengikut)
                    <div style="white-space: nowrap; line-height: 11.8pt; {{ $fitFont($pengikut->jabatan, 32) }}">{{ $pengikut->jabatan ?: '-' }}</div>
                @empty
                    &nbsp;
                @endforelse
            </td>
        </tr>
        <tr>
            <td class="no">10</td>
            <td>Pembebanan Anggaran{!! $li('a.', 'Instansi', 11) !!}{!! $li('b.', 'Akun', 11) !!}</td>
            <td>
                <div style="line-height: 11.8pt;">&nbsp;</div>
                <div style="line-height: 11.8pt;">{{ $sekolah }}</div>
                <div style="line-height: 11.8pt; white-space: nowrap; {{ $fitFont($akun, 32) }}">{{ $akun }}&nbsp;</div>
            </td>
        </tr>
        <tr>
            <td class="no">11</td>
            <td>Keterangan lain-lain</td>
            <td style="font-size: 8.5pt;">{{ $keterangan }}&nbsp;</td>
        </tr>
    </table>

    <div class="t" style="left: {{ $u(85) }}; top: {{ $gc(1069) }};">*)coret yang tidak perlu</div>

    {{-- Tanda tangan pengesahan (sisi depan) --}}
    <table class="kv" style="left: {{ $u(513) }}; top: {{ $gc(1098) }}; width: {{ $u(460) }};">
        <tr><td style="width: {{ $u(187) }};">Dikeluarkan di</td><td style="width: {{ $u(15) }};">:</td><td>Karawang</td></tr>
        <tr><td>Tanggal</td><td>:</td><td>{{ $tanggalTerbit }}</td></tr>
    </table>
    <div class="t" style="left: {{ $u(513) }}; top: {{ $gc(1155) }};">Kuasa Pengguna Anggaran</div>
    @if($ttdPath)
        <img class="abs" src="{{ $ttdPath }}" style="left: {{ $u(513) }}; top: {{ $gy(1170) }}; height: {{ $u(90) }};">
    @endif
    <div class="t nama" style="left: {{ $u(513) }}; top: {{ $gc(1270) }};">{{ $namaPejabat }}</div>
    <div class="t" style="left: {{ $u(513) }}; top: {{ $gc(1298) }};">NIP. -,-</div>

    {{-- Sisi belakang: kotak dan garis pemisah --}}
    <div class="garis" style="left: {{ $u(1006) }}; top: {{ $u(63) }}; width: {{ $u(943) }}; height: {{ $u(2) }};"></div>
    <div class="garis" style="left: {{ $u(1006) }}; top: {{ $u(1300) }}; width: {{ $u(943) }}; height: {{ $u(2) }};"></div>
    <div class="garis" style="left: {{ $u(1006) }}; top: {{ $u(63) }}; width: {{ $u(2) }}; height: {{ $u(1239) }};"></div>
    <div class="garis" style="left: {{ $u(1947) }}; top: {{ $u(63) }}; width: {{ $u(2) }}; height: {{ $u(1239) }};"></div>
    <div class="garis" style="left: {{ $u(1460) }}; top: {{ $u(63) }}; width: {{ $u(2) }}; height: {{ $u(604) }};"></div>
    @foreach ([350, 667, 985] as $y)
        <div class="garis" style="left: {{ $u(1006) }}; top: {{ $u($y) }}; width: {{ $u(943) }}; height: {{ $u(2) }};"></div>
    @endforeach

    @foreach ($blok as $b)
        <table class="kv" style="left: {{ $u($b['x']) }}; top: {{ $tengah($b['y'] + 14) }}; width: {{ $u(array_sum($b['w'])) }};">
            @foreach ($b['baris'] as $i => [$label, $nilai])
                <tr>
                    @if($b['w'][0])
                        <td style="width: {{ $u($b['w'][0]) }};">{{ $i === 0 ? $b['romawi'] : '' }}</td>
                    @endif
                    <td style="width: {{ $u($b['w'][1]) }};">{{ $label }}</td>
                    <td style="width: {{ $u($b['w'][2]) }};">:</td>
                    <td style="width: {{ $u($b['w'][3]) }};">{{ $nilai }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <div class="t" style="left: {{ $u(1063) }}; top: {{ $tengah(653) }};">{{ str_repeat('-', 47) }}</div>
    <div class="t" style="left: {{ $u(1540) }}; top: {{ $tengah(653) }};">{{ str_repeat('-', 47) }}</div>
    @if($catatanKonfirmasi)
        <div class="abs" style="left: {{ $u(1063) }}; width: {{ $u(380) }}; top: {{ $u(604) }}; font-size: 6.3pt; line-height: 8pt; color: #222;">{{ $catatanKonfirmasi }}</div>
        <div class="abs" style="left: {{ $u(1540) }}; width: {{ $u(380) }}; top: {{ $u(604) }}; font-size: 6.3pt; line-height: 8pt; color: #222;">{{ $catatanKonfirmasi }}</div>
    @endif

    {{-- Kotak pertama: berangkat dari sekolah, ditandatangani pejabat --}}
    @if($ttdPath)
        <img class="abs" src="{{ $ttdPath }}" style="left: {{ $u(1650) }}; top: {{ $u(225) }}; height: {{ $u(90) }};">
    @endif
    <div class="t" style="left: {{ $u(1650) }}; top: {{ $tengah(337) }};">{{ $namaPejabat }}</div>

    {{-- Pemeriksaan akhir (III) --}}
    <div class="t" style="left: {{ $u(1057) }}; top: {{ $tengah(1059) }};">Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya</div>
    <div class="t" style="left: {{ $u(1057) }}; top: {{ $tengah(1088) }};">dan semata-mata untuk kepentingan jabatan dalam waktu sesingkat-singkatnya</div>
    <div class="t" style="left: {{ $u(1301) }}; top: {{ $tengah(1116) }};">Kuasa Pengguna Anggaran</div>
    @if($ttdPath)
        <img class="abs" src="{{ $ttdPath }}" style="left: {{ $u(1301) }}; top: {{ $u(1132) }}; height: {{ $u(100) }};">
    @endif
    <div class="t nama" style="left: {{ $u(1301) }}; top: {{ $tengah(1259) }};">{{ $namaPejabat }}</div>
    <div class="t" style="left: {{ $u(1301) }}; top: {{ $tengah(1288) }};">NIP. -,-</div>

    {{-- Tanda Tangan Elektronik (TTE): QR verifikasi + sidik jari --}}
    @if($tteQr)
        <img class="abs" src="{{ $tteQr }}" style="left: {{ $u(85) }}; top: {{ $gy(1110) }}; width: {{ $u(135) }}; height: {{ $u(135) }};">
        <div class="t" style="left: {{ $u(65) }}; width: {{ $u(175) }}; top: {{ $gy(1248) }}; font-size: 6.3pt; line-height: 8pt; text-align: center;">TTE {{ $tteSidik }}</div>

        <img class="abs" src="{{ $tteQr }}" style="left: {{ $u(1480) }}; top: {{ $u(195) }}; width: {{ $u(135) }}; height: {{ $u(135) }};">

        <img class="abs" src="{{ $tteQr }}" style="left: {{ $u(1610) }}; top: {{ $u(1138) }}; width: {{ $u(135) }}; height: {{ $u(135) }};">
        <div class="t" style="left: {{ $u(1590) }}; width: {{ $u(175) }}; top: {{ $u(1277) }}; font-size: 6.3pt; line-height: 8pt; text-align: center;">TTE {{ $tteSidik }}</div>

        <div class="abs" style="left: {{ $u(240) }}; width: {{ $u(250) }}; top: {{ $gy(1112) }}; font-size: 6pt; line-height: 8pt; color: #222;">
            Dokumen ini ditandatangani elektronik (TTE) oleh Kepala Sekolah. Periksa keasliannya dengan memindai QR di samping atau membuka:<br>
            {{ \Illuminate\Support\Str::beforeLast($tteUrl, '/') }}/<br>
            {{ \Illuminate\Support\Str::afterLast($tteUrl, '/') }}
        </div>
    @endif
</body>
</html>
