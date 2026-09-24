@php
    // Data tampilan dipakai bersama semua template (lihat SppdPdfData) agar isi dokumen selalu sama.
    extract(app(\App\Services\Sppd\SppdPdfData::class)->data($pengajuan));

    $pratinjau ??= false;

    /** Baris "a. teks" dengan indentasi menggantung. */
    $li = fn (string $huruf, ?string $teks, int $lebar = 16): string => '<table class="li"><tr>'
        .'<td style="width: '.$lebar.'pt;">'.e($huruf).'</td>'
        .'<td>'.($teks === null || $teks === '' ? '&nbsp;' : e($teks)).'</td></tr></table>';

    /** Blok "label : nilai" (sisi belakang). */
    $kv = function (array $baris, int $lebarLabel = 72): string {
        $html = '<table class="kv">';
        foreach ($baris as [$label, $nilai]) {
            $html .= '<tr><td style="width: '.$lebarLabel.'pt;">'.e($label).'</td><td style="width: 8pt;">:</td><td>'.($nilai === null || $nilai === '' ? '&nbsp;' : e($nilai)).'</td></tr>';
        }

        return $html.'</table>';
    };

    $pejabatTujuan = $kedatangan
        ? [['Kepala', $kedatangan->pejabat_nama], ['Jabatan', $kedatangan->pejabat_jabatan]]
        : [['Kepala', '']];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPPD {{ $sppd->nomor_sppd }}</title>
    <style>
        @page { margin: 26pt 34pt 30pt 34pt; }
        body { margin: 0; font-family: Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.3; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        .kop td { vertical-align: middle; }
        .kop .teks div { text-align: center; }
        .garis-tebal { border-top: 2.6pt solid #000; margin-top: 5pt; }
        .garis-tipis { border-top: 0.8pt solid #000; margin-top: 1.6pt; }

        table.meta { width: auto; margin-top: 12pt; }
        table.meta td { padding: 0.5pt 0; }

        .judul { text-align: center; font-size: 11.5pt; font-weight: bold; text-decoration: underline; margin: 12pt 0 9pt; }

        table.isian td { border: 0.7pt solid #000; padding: 3.2pt 0 3.2pt 6pt; }
        table.isian td.no { text-align: center; padding: 3.2pt 0; color: #000; }
        table.isian td.mid { vertical-align: middle; }
        table.li { width: 100%; }
        table.isian table.li td, table.li td { border: 0; padding: 0; }
        table.isian .baris { line-height: 1.3; }

        table.kv { width: 100%; }
        table.kv td { padding: 1.2pt 0; }

        .kecil { font-size: 7.4pt; color: #222; line-height: 1.25; }
        .nama { font-weight: bold; text-decoration: underline; }

        table.belakang td.sel { border: 0.7pt solid #000; padding: 9pt 11pt; }
        .sidik { font-size: 6.4pt; color: #222; text-align: left; white-space: nowrap; }
        .judul2 { font-size: 11pt; font-weight: bold; margin: 0 0 8pt; }
        .judul2 span { font-weight: normal; font-size: 9pt; color: #333; }
        .garis-tanda { border-bottom: 0.7pt dashed #000; height: 34pt; }

        .pratinjau { position: fixed; bottom: -14pt; left: 0; right: 0; text-align: center; font-size: 7pt; color: #c00000; }
    </style>
</head>
<body>
    @if($pratinjau)
        <div class="pratinjau">PRATINJAU TEMPLATE &mdash; DATA CONTOH, BUKAN DOKUMEN RESMI</div>
    @endif

    {{-- ===== Halaman 1: isian SPPD ===== --}}
    <table class="kop">
        <tr>
            <td style="width: 70pt;"><img src="{{ public_path('logo_smk.png') }}" style="width: 62pt;"></td>
            <td class="teks">
                <div style="font-size: 10pt; font-weight: bold;">YAYASAN AL-ULYA KARAWANG</div>
                <div style="font-size: 19pt; font-weight: bold; line-height: 1.05;">SMK BUDI MULIA</div>
                <div style="font-size: 8.4pt; font-weight: bold;">Terakreditasi A, berdasarkan SK BAN-SM nomor 763/BAN-SM/SK/2019</div>
                <div style="font-size: 8.4pt; font-weight: bold;">Jl. Ciherang Wadas, Kec. Telukjambe Timur Kab. Karawang 41361</div>
                <div style="font-family: 'Times-Roman', serif; font-size: 10pt; font-weight: bold;">Telepon (0267) 8458459 e-Mail smk_budimulia@teachers.org</div>
            </td>
            <td style="width: 70pt;">&nbsp;</td>
        </tr>
    </table>
    <div class="garis-tebal"></div>
    <div class="garis-tipis"></div>

    <table class="meta">
        <tr><td style="width: 62pt;">Lampiran</td><td style="width: 10pt;">:</td><td>Surat Perjalanan Dinas</td></tr>
        <tr><td>Nomor</td><td>:</td><td>{{ $sppd->nomor_sppd }}</td></tr>
        <tr><td>Tanggal</td><td>:</td><td>{{ $tanggalTerbit }}</td></tr>
    </table>

    <div class="judul">SURAT PERJALANAN DINAS (SPPD)</div>

    <table class="isian">
        <tr>
            <td class="no" style="width: 26pt;">1</td>
            <td style="width: 199pt;">Pengguna Anggaran/kuasa Pengguna Anggaran</td>
            <td style="width: 290pt;">{{ $namaPejabat }}</td>
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
            <td>{{ $alatAngkutan }}&nbsp;</td>
        </tr>
        <tr>
            <td class="no">6</td>
            <td>{!! $li('a.', 'Tempat Berangkat') !!}{!! $li('b.', 'Tempat tujuan') !!}</td>
            <td>{!! $li('a.', $sekolah, 14) !!}{!! $li('b.', $tujuan, 14) !!}</td>
        </tr>
        <tr>
            <td class="no">7</td>
            <td>{!! $li('a.', 'Lama berangkat') !!}{!! $li('b.', 'Tanggal berangkat') !!}{!! $li('c.', 'Tanggal harus kembali/tiba di tempat baru*)') !!}</td>
            <td>{!! $li('a.', $lamaHari.' Hari', 14) !!}{!! $li('b.', $tanggalBerangkat.', '.$jamBerangkat.' WIB', 14) !!}{!! $li('c.', $tanggalKembali.', '.$jamKembali.' WIB', 14) !!}</td>
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
                    <div class="baris">{{ $i + 1 }}. {{ $pengikut->name }}</div>
                @empty
                    &nbsp;
                @endforelse
            </td>
            <td>
                @forelse ($pengikuts as $pengikut)
                    <div class="baris">{{ $pengikut->jabatan ?: '-' }}</div>
                @empty
                    &nbsp;
                @endforelse
            </td>
        </tr>
        <tr>
            <td class="no">10</td>
            <td>Pembebanan Anggaran{!! $li('a.', 'Instansi', 12) !!}{!! $li('b.', 'Akun', 12) !!}</td>
            <td>
                <div class="baris">&nbsp;</div>
                <div class="baris">{{ $sekolah }}</div>
                <div class="baris">{{ $akun }}&nbsp;</div>
            </td>
        </tr>
        <tr>
            <td class="no">11</td>
            <td>Keterangan lain-lain</td>
            <td>{{ $keterangan }}&nbsp;</td>
        </tr>
    </table>
    <div style="font-size: 8.4pt; margin-top: 3pt;">*)coret yang tidak perlu</div>

    <table style="margin-top: 12pt;">
        <tr>
            <td style="width: 246pt; padding-right: 14pt;">
                @if($tteQr)
                    <table>
                        <tr>
                            <td style="width: 74pt;">
                                <img src="{{ $tteQr }}" style="width: 68pt; height: 68pt;">
                                <div class="sidik" style="width: 68pt;">TTE {{ $tteSidik }}</div>
                            </td>
                            <td class="kecil" style="padding-left: 4pt;">
                                Dokumen ini ditandatangani elektronik (TTE) oleh Kepala Sekolah. Periksa keasliannya dengan memindai QR atau membuka:<br>
                                {{ \Illuminate\Support\Str::beforeLast($tteUrl, '/') }}/<br>{{ \Illuminate\Support\Str::afterLast($tteUrl, '/') }}
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
            <td>
                {!! $kv([['Dikeluarkan di', 'Karawang'], ['Tanggal', $tanggalTerbit]], 78) !!}
                <div style="margin-top: 3pt;">Kuasa Pengguna Anggaran</div>
                <div style="height: 46pt;">
                    @if($ttdPath)
                        <img src="{{ $ttdPath }}" style="height: 44pt; margin-top: 2pt;">
                    @endif
                </div>
                <div class="nama">{{ $namaPejabat }}</div>
                <div>NIP. -,-</div>
            </td>
        </tr>
    </table>

    {{-- ===== Halaman 2: pengesahan perjalanan (sisi belakang) ===== --}}
    <div style="page-break-before: always;"></div>

    <div class="judul2">Pengesahan Perjalanan <span>&mdash; SPPD Nomor {{ $sppd->nomor_sppd }}</span></div>

    <table class="belakang">
        <tr>
            <td class="sel" colspan="2">
                <table>
                    <tr>
                        <td style="width: 300pt; padding-right: 10pt;">
                            {!! $kv([['Berangkat dari', $sekolah], ['Ke', $tujuan], ['Pada Tanggal', $tanggalBerangkat]], 76) !!}
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td style="width: 70pt;">
                                        @if($tteQr)
                                            <img src="{{ $tteQr }}" style="width: 62pt; height: 62pt;">
                                        @endif
                                    </td>
                                    <td>
                                        <div style="height: 46pt;">
                                            @if($ttdPath)
                                                <img src="{{ $ttdPath }}" style="height: 44pt;">
                                            @endif
                                        </div>
                                        <div>{{ $namaPejabat }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="sel" style="width: 50%;">
                <div style="height: 92pt;">{!! $kv([['Tiba di', $tujuan], ['Pada Tanggal', $tibaTanggal], ...$pejabatTujuan], 66) !!}</div>
                <div class="garis-tanda"></div>
                @if($catatanKonfirmasi)
                    <div class="kecil" style="margin-top: 3pt;">{{ $catatanKonfirmasi }}</div>
                @endif
            </td>
            <td class="sel">
                <div style="height: 92pt;">{!! $kv([['Berangkat dari', $tujuan], ['Ke', $sekolah], ['Pada Tanggal', $pulangTanggal], ...$pejabatTujuan], 66) !!}</div>
                <div class="garis-tanda"></div>
                @if($catatanKonfirmasi)
                    <div class="kecil" style="margin-top: 3pt;">{{ $catatanKonfirmasi }}</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="sel" colspan="2">
                <table>
                    <tr>
                        <td style="width: 24pt;"><strong>III.</strong></td>
                        <td>
                            {!! $kv([['Tiba kembali di', 'Karawang'], ['Pada tanggal', $tanggalKembali]], 80) !!}
                            <div style="margin-top: 5pt;">
                                Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya dan semata-mata untuk
                                kepentingan jabatan dalam waktu sesingkat-singkatnya.
                            </div>
                        </td>
                    </tr>
                </table>
                <table style="margin-top: 8pt;">
                    <tr>
                        <td style="width: 268pt;">
                            @if($tteQr)
                                <img src="{{ $tteQr }}" style="width: 62pt; height: 62pt;">
                                <div class="sidik" style="width: 62pt;">TTE {{ $tteSidik }}</div>
                            @endif
                        </td>
                        <td>
                            <div>Kuasa Pengguna Anggaran</div>
                            <div style="height: 46pt;">
                                @if($ttdPath)
                                    <img src="{{ $ttdPath }}" style="height: 44pt; margin-top: 2pt;">
                                @endif
                            </div>
                            <div class="nama">{{ $namaPejabat }}</div>
                            <div>NIP. -,-</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
