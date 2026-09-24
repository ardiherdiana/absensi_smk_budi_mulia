@php
    // Data tampilan dipakai bersama semua template (lihat SppdPdfData) agar isi dokumen selalu sama.
    extract(app(\App\Services\Sppd\SppdPdfData::class)->data($pengajuan));

    $pratinjau ??= false;

    /** Baris "a. teks" dengan indentasi menggantung. */
    $li = fn (string $huruf, ?string $teks, int $lebar = 13): string => '<table class="li"><tr>'
        .'<td style="width: '.$lebar.'pt;">'.e($huruf).'</td>'
        .'<td>'.($teks === null || $teks === '' ? '&nbsp;' : e($teks)).'</td></tr></table>';

    /** Blok "label : nilai" (sisi belakang). */
    $kv = function (array $baris, int $lebarLabel = 58): string {
        $html = '<table class="kv">';
        foreach ($baris as [$label, $nilai]) {
            $html .= '<tr><td style="width: '.$lebarLabel.'pt;">'.e($label).'</td><td style="width: 6pt;">:</td><td>'.($nilai === null || $nilai === '' ? '&nbsp;' : e($nilai)).'</td></tr>';
        }

        return $html.'</table>';
    };

    $pejabatTujuan = $kedatangan
        ? [['Kepala', $kedatangan->pejabat_nama], ['Jabatan', $kedatangan->pejabat_jabatan]]
        : [['Kepala', '']];

    /*
     * Kolom form berukuran tetap dan template ini wajib satu halaman. Isi yang panjang (maksud, pengikut,
     * keterangan, akun) diperkirakan dalam jumlah baris, lalu ukuran font tabel diturunkan bertahap.
     */
    $perkiraanBaris = fn (?string $teks, int $perBaris): int => $teks === null || $teks === '' ? 1 : (int) ceil(mb_strlen($teks) / $perBaris);
    $barisPengikut = max(1, $pengikuts->sum(fn ($p) => $perkiraanBaris($p->name, 30)), $pengikuts->sum(fn ($p) => $perkiraanBaris($p->jabatan, 42)));
    $beban = $perkiraanBaris($pengajuan->maksud, 50)
        + $barisPengikut
        + $perkiraanBaris($keterangan, 55)
        + $perkiraanBaris($akun, 50)
        + $perkiraanBaris($alatAngkutan, 50)
        + $perkiraanBaris($tujuan, 46)
        + $perkiraanBaris($pemohon->jabatan, 46);
    [$fontTabel, $spasiSel] = match (true) {
        $beban <= 12 => [8.3, 2.0],
        $beban <= 16 => [7.6, 1.5],
        $beban <= 21 => [7.0, 1.2],
        $beban <= 27 => [6.4, 1.0],
        default => [6.0, 0.8],
    };

    // Tinggi blok tiba/berangkat di sisi belakang mengikuti panjang tujuan (kolom sempit, ±23 karakter per baris).
    $tinggiBlok = max(92, ($perkiraanBaris($tujuan, 22) + 7.5) * 10.6);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPPD {{ $sppd->nomor_sppd }}</title>
    <style>
        @page { margin: 20pt 26pt 22pt 26pt; }
        body { margin: 0; font-family: Helvetica, sans-serif; font-size: 8.3pt; line-height: 1.22; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        table.halaman > tbody > tr > td { vertical-align: top; }
        td.depan { width: 398pt; padding-right: 12pt; }
        td.belakang-kolom { padding-left: 14pt; border-left: 0.7pt dashed #666; }

        .kop td { vertical-align: middle; }
        .kop .teks div { text-align: center; }
        .garis-tebal { border-top: 2.2pt solid #000; margin-top: 3pt; }
        .garis-tipis { border-top: 0.7pt solid #000; margin-top: 1.3pt; }

        table.meta { width: auto; margin-top: 7pt; }
        table.meta td { padding: 0.2pt 0; }

        .judul { text-align: center; font-size: 10pt; font-weight: bold; text-decoration: underline; margin: 7pt 0 6pt; }

        table.isian td { border: 0.6pt solid #000; padding: {{ $spasiSel }}pt 0 {{ $spasiSel }}pt 5pt; font-size: {{ $fontTabel }}pt; }
        table.isian td.no { text-align: center; padding: {{ $spasiSel }}pt 0; }
        table.isian td.mid { vertical-align: middle; }
        table.li { width: 100%; }
        table.isian table.li td, table.li td { border: 0; padding: 0; }

        table.kv { width: 100%; }
        table.kv td { padding: 0.9pt 0; }

        .kecil { font-size: 6.4pt; color: #222; line-height: 1.22; }
        .sidik { font-size: 6pt; color: #222; text-align: left; white-space: nowrap; }
        .nama { font-weight: bold; text-decoration: underline; }

        .judul2 { font-size: 9.6pt; font-weight: bold; margin: 0 0 6pt; }
        .judul2 span { font-weight: normal; font-size: 7.8pt; color: #333; }
        table.belakang td.sel { border: 0.6pt solid #000; padding: 6pt 8pt; }
        .garis-tanda { border-bottom: 0.6pt dashed #000; height: 20pt; }

        .pratinjau { position: fixed; bottom: -6pt; left: 0; right: 0; text-align: center; font-size: 6.5pt; color: #c00000; }
    </style>
</head>
<body>
    @if($pratinjau)
        <div class="pratinjau">PRATINJAU TEMPLATE &mdash; DATA CONTOH, BUKAN DOKUMEN RESMI</div>
    @endif

    <table class="halaman">
        <tr>
            {{-- ===== Sisi depan: isian SPPD ===== --}}
            <td class="depan">
                <table class="kop">
                    <tr>
                        <td style="width: 50pt;"><img src="{{ public_path('logo_smk.png') }}" style="width: 46pt;"></td>
                        <td class="teks">
                            <div style="font-size: 8.2pt; font-weight: bold;">YAYASAN AL-ULYA KARAWANG</div>
                            <div style="font-size: 15pt; font-weight: bold; line-height: 1.05;">SMK BUDI MULIA</div>
                            <div style="font-size: 6.7pt; font-weight: bold;">Terakreditasi A, berdasarkan SK BAN-SM nomor 763/BAN-SM/SK/2019</div>
                            <div style="font-size: 6.7pt; font-weight: bold;">Jl. Ciherang Wadas, Kec. Telukjambe Timur Kab. Karawang 41361</div>
                            <div style="font-family: 'Times-Roman', serif; font-size: 7.6pt; font-weight: bold;">Telepon (0267) 8458459 e-Mail smk_budimulia@teachers.org</div>
                        </td>
                        <td style="width: 50pt;">&nbsp;</td>
                    </tr>
                </table>
                <div class="garis-tebal"></div>
                <div class="garis-tipis"></div>

                <table class="meta">
                    <tr><td style="width: 50pt;">Lampiran</td><td style="width: 8pt;">:</td><td>Surat Perjalanan Dinas</td></tr>
                    <tr><td>Nomor</td><td>:</td><td>{{ $sppd->nomor_sppd }}</td></tr>
                    <tr><td>Tanggal</td><td>:</td><td>{{ $tanggalTerbit }}</td></tr>
                </table>

                <div class="judul">SURAT PERJALANAN DINAS (SPPD)</div>

                <table class="isian">
                    <tr>
                        <td class="no" style="width: 20pt;">1</td>
                        <td style="width: 148pt;">Pengguna Anggaran/kuasa Pengguna Anggaran</td>
                        <td style="width: 225pt;">{{ $namaPejabat }}</td>
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
                        <td>{!! $li('a.', $sekolah) !!}{!! $li('b.', $tujuan) !!}</td>
                    </tr>
                    <tr>
                        <td class="no">7</td>
                        <td>{!! $li('a.', 'Lama berangkat') !!}{!! $li('b.', 'Tanggal berangkat') !!}{!! $li('c.', 'Tanggal harus kembali/tiba di tempat baru*)') !!}</td>
                        <td>{!! $li('a.', $lamaHari.' Hari') !!}{!! $li('b.', $tanggalBerangkat.', '.$jamBerangkat.' WIB') !!}{!! $li('c.', $tanggalKembali.', '.$jamKembali.' WIB') !!}</td>
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
                                <div>{{ $i + 1 }}. {{ $pengikut->name }}</div>
                            @empty
                                &nbsp;
                            @endforelse
                        </td>
                        <td>
                            @forelse ($pengikuts as $pengikut)
                                <div>{{ $pengikut->jabatan ?: '-' }}</div>
                            @empty
                                &nbsp;
                            @endforelse
                        </td>
                    </tr>
                    <tr>
                        <td class="no">10</td>
                        <td>Pembebanan Anggaran{!! $li('a.', 'Instansi', 11) !!}{!! $li('b.', 'Akun', 11) !!}</td>
                        <td>
                            <div>&nbsp;</div>
                            <div>{{ $sekolah }}</div>
                            <div>{{ $akun }}&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="no">11</td>
                        <td>Keterangan lain-lain</td>
                        <td>{{ $keterangan }}&nbsp;</td>
                    </tr>
                </table>
                <div style="font-size: 7pt; margin-top: 2pt;">*)coret yang tidak perlu</div>

                <table style="margin-top: 7pt;">
                    <tr>
                        <td style="width: 196pt; padding-right: 8pt;">
                            @if($tteQr)
                                <table>
                                    <tr>
                                        <td style="width: 58pt;">
                                            <img src="{{ $tteQr }}" style="width: 54pt; height: 54pt;">
                                            <div class="sidik">TTE {{ $tteSidik }}</div>
                                        </td>
                                        <td class="kecil" style="padding-left: 3pt;">
                                            Dokumen ini ditandatangani elektronik (TTE) oleh Kepala Sekolah. Periksa keasliannya dengan memindai QR atau membuka:<br>
                                            {{ \Illuminate\Support\Str::beforeLast($tteUrl, '/') }}/<br>{{ \Illuminate\Support\Str::afterLast($tteUrl, '/') }}
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                        <td>
                            {!! $kv([['Dikeluarkan di', 'Karawang'], ['Tanggal', $tanggalTerbit]], 62) !!}
                            <div style="margin-top: 1pt;">Kuasa Pengguna Anggaran</div>
                            <div style="height: 32pt;">
                                @if($ttdPath)
                                    <img src="{{ $ttdPath }}" style="height: 30pt; margin-top: 1pt;">
                                @endif
                            </div>
                            <div class="nama">{{ $namaPejabat }}</div>
                            <div>NIP. -,-</div>
                        </td>
                    </tr>
                </table>
            </td>

            {{-- ===== Sisi belakang: pengesahan perjalanan ===== --}}
            <td class="belakang-kolom">
                <div class="judul2">Pengesahan Perjalanan <span>&mdash; SPPD Nomor {{ $sppd->nomor_sppd }}</span></div>

                <table class="belakang">
                    <tr>
                        <td class="sel" colspan="2">
                            {!! $kv([['Berangkat dari', $sekolah], ['Ke', $tujuan], ['Pada Tanggal', $tanggalBerangkat]], 62) !!}
                            <table style="margin-top: 4pt;">
                                <tr>
                                    <td style="width: 52pt;">
                                        @if($tteQr)
                                            <img src="{{ $tteQr }}" style="width: 46pt; height: 46pt;">
                                        @endif
                                    </td>
                                    <td>
                                        <div style="height: 34pt;">
                                            @if($ttdPath)
                                                <img src="{{ $ttdPath }}" style="height: 32pt;">
                                            @endif
                                        </div>
                                        <div>{{ $namaPejabat }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="sel" style="width: 50%;">
                            <div style="height: {{ $tinggiBlok }}pt;">{!! $kv([['Tiba di', $tujuan], ['Pada Tanggal', $tibaTanggal], ...$pejabatTujuan], 62) !!}</div>
                            <div class="garis-tanda"></div>
                            @if($catatanKonfirmasi)
                                <div class="kecil" style="margin-top: 2pt;">{{ $catatanKonfirmasi }}</div>
                            @endif
                        </td>
                        <td class="sel">
                            <div style="height: {{ $tinggiBlok }}pt;">{!! $kv([['Berangkat dari', $tujuan], ['Ke', $sekolah], ['Pada Tanggal', $pulangTanggal], ...$pejabatTujuan], 62) !!}</div>
                            <div class="garis-tanda"></div>
                            @if($catatanKonfirmasi)
                                <div class="kecil" style="margin-top: 2pt;">{{ $catatanKonfirmasi }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="sel" colspan="2">
                            <table>
                                <tr>
                                    <td style="width: 20pt;"><strong>III.</strong></td>
                                    <td>
                                        {!! $kv([['Tiba kembali di', 'Karawang'], ['Pada tanggal', $tanggalKembali]], 66) !!}
                                        <div style="margin-top: 3pt;">
                                            Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya dan semata-mata untuk
                                            kepentingan jabatan dalam waktu sesingkat-singkatnya.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="margin-top: 5pt;">
                                <tr>
                                    <td style="width: 150pt;">
                                        @if($tteQr)
                                            <img src="{{ $tteQr }}" style="width: 50pt; height: 50pt;">
                                            <div class="sidik">TTE {{ $tteSidik }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>Kuasa Pengguna Anggaran</div>
                                        <div style="height: 34pt;">
                                            @if($ttdPath)
                                                <img src="{{ $ttdPath }}" style="height: 32pt; margin-top: 1pt;">
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
            </td>
        </tr>
    </table>
</body>
</html>
