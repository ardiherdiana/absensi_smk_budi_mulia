@php
    /*
     * Meniru "Format Supervisi Guru.docx": A4, margin atas 3 cm / kanan 2 cm / bawah 2 cm / kiri 2,5 cm,
     * Arial (Helvetica di dompdf, ukuran huruf sama), tabel 4 kolom dengan lebar 699/3700/2796/1856 twips,
     * garis tipis di bagian atas halaman (border bawah tabel header docx).
     *
     * Word memberi jarak "tunggal" 1,149 x ukuran huruf untuk Arial; "1,2 baris" (line=288) = 1,379 x.
     */
    $tanggal = $supervisi['tanggal'];
    $tunggal = \App\Support\Pguru\SupervisiInstrumen::BUTIR_SPASI_TUNGGAL;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Instrumen Observasi Implementasi dan Refleksi Perencanaan Pembelajaran</title>
    <style>
        @page { margin: 85.05pt 56.7pt 56.7pt 70.9pt; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 11pt; color: #000; }
        .garis-atas { position: fixed; top: -36.6pt; left: 0; width: 453pt; height: 0; border-top: 0.75pt solid #000; }
        .judul { text-align: center; font-size: 12pt; line-height: 13.8pt; margin: 0 0 30pt; }
        .kepala { width: 100%; border-collapse: collapse; font-size: 12pt; }
        .kepala td { padding: 0; line-height: 20.7pt; vertical-align: top; border: 0; }
        .petunjuk { font-size: 12pt; line-height: 20.7pt; margin: 9pt 0 0; text-align: justify; }
        table.instrumen { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .instrumen th, .instrumen td { border: 1pt solid #000; padding: 5pt; vertical-align: top; text-align: left; line-height: 15.2pt; }
        .instrumen th { background: #f2f2f2; font-weight: bold; text-align: center; }
        .instrumen td.no { text-align: center; }
        .instrumen td.bagian { line-height: 12.64pt; }
        .instrumen tr { page-break-inside: avoid; }
        .instrumen td.tunggal { line-height: 12.64pt; }
        /* Daftar bernomor huruf (a. b. c.) dengan menjorok gantung 227 twips, seperti numbering di docx. */
        .instrumen table.daftar { width: 100%; border-collapse: collapse; }
        .instrumen table.daftar td { border: 0; padding: 0; line-height: 12.64pt; vertical-align: top; }
        .instrumen table.daftar td.huruf { width: 11.35pt; }
        .jawaban { min-height: 45pt; }
        .penutup { page-break-inside: avoid; margin-top: 30pt; }
        .penutup table { width: 100%; border-collapse: collapse; }
        .penutup td { padding: 0; vertical-align: top; line-height: 12.64pt; }
        .tanda-tangan { height: 62pt; }
    </style>
</head>
<body>
    <div class="garis-atas"></div>
    <p class="judul">INSTRUMEN OBSERVASI IMPLEMENTASI DAN REFLEKSI<br>PERENCANAAN PEMBELAJARAN<br>&nbsp;</p>

    <table class="kepala">
        <tr><td style="width:134.7pt">Penyusun Perencanaan</td><td style="width:7.05pt">:</td><td>{{ $supervisi['guruDinilai'] }}</td></tr>
        <tr><td>Pemberi Umpan Balik</td><td>:</td><td>{{ $supervisi['pemberiUmpanBalik'] }}</td></tr>
        <tr><td>Hari/Tanggal</td><td>:</td><td>{{ $tanggal->locale('id')->translatedFormat('l, j F Y') }}</td></tr>
    </table>

    <p class="petunjuk">Berikan umpan balik terhadap praktik yang telah dilakukan dengan menggunakan instrumen berikut!</p>

    <table class="instrumen">
        <thead>
            <tr>
                <th style="width: 7.72%">No.</th>
                <th style="width: 40.88%">Aspek yang diamati</th>
                <th style="width: 30.89%">Bukti Pembelajaran</th>
                <th style="width: 20.51%">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bagian as $b)
                <tr><td colspan="4" class="bagian">{{ $b['judul'] }}</td></tr>
                @foreach ($b['butir'] as $butir)
                    @php $isi = $supervisi['butir'][$butir['nomor'] - 1]; @endphp
                    <tr @if ($butir['nomor'] === 7) style="height: 74.45pt" @endif>
                        <td class="no {{ in_array($butir['nomor'], $tunggal) ? 'tunggal' : '' }}">{{ $butir['nomor'] }}</td>
                        <td class="{{ in_array($butir['nomor'], $tunggal) ? 'tunggal' : '' }}">
                            @php $urut = 0; @endphp
                            @foreach ($butir['paragraf'] as $p)
                                @if ($p['daftar'])
                                    <table class="daftar"><tr><td class="huruf">{{ chr(96 + ++$urut) }}.</td><td>{{ $p['teks'] }}</td></tr></table>
                                @else
                                    <div>{{ $p['teks'] }}</div>
                                @endif
                            @endforeach
                        </td>
                        <td>{!! nl2br(e($isi['bukti'])) !!}</td>
                        <td>{!! nl2br(e($isi['catatan'])) !!}</td>
                    </tr>
                @endforeach
            @endforeach

            <tr><td colspan="4" class="bagian">Refleksi</td></tr>
            @foreach ($refleksi as $i => $r)
                <tr>
                    <td class="no">{{ $r['nomor'] }}</td>
                    <td colspan="3">
                        <div>{{ $r['pertanyaan'] }}</div>
                        <div class="jawaban">{!! nl2br(e($supervisi['refleksi'][$i])) !!}</div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="penutup">
        <table>
            <tr>
                <td style="width: 252pt">Mensupervisi,</td>
                <td>{{ config('pguru.kota') }}, {{ $tanggal->locale('id')->translatedFormat('j F Y') }}</td>
            </tr>
        </table>
        <div class="tanda-tangan">&nbsp;</div>
        <div style="line-height: 12.64pt">{{ $supervisi['pemberiUmpanBalik'] }}</div>
    </div>
</body>
</html>
