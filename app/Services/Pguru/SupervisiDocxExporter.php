<?php

namespace App\Services\Pguru;

use App\Models\Pguru\Supervisi;
use App\Support\Pguru\SupervisiInstrumen;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Mengisi template resmi "Format Supervisi Guru.docx" (resources/pguru/format-supervisi-guru.docx)
 * dengan menyunting document.xml aslinya: font Arial, tabel, tab-stop, dan bingkai tetap milik
 * template. Yang ditulis hanya isian: nama, tanggal, kolom Bukti/Catatan, jawaban refleksi, dan nama
 * penanda tangan di bagian bawah.
 */
class SupervisiDocxExporter
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_XML = 'http://www.w3.org/XML/1998/namespace';

    /** Baris titik-titik di template yang menjadi tempat nama penanda tangan. */
    private const GARIS_NAMA = '..........................';

    /**
     * @return string path berkas .docx sementara; hapus setelah dikirim
     */
    public function buat(Supervisi $supervisi): string
    {
        $tujuan = tempnam(sys_get_temp_dir(), 'pguru-svs-');
        if ($tujuan === false || ! copy(config('pguru.templates.supervisi'), $tujuan)) {
            throw new RuntimeException('Template supervisi tidak dapat disalin.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tujuan) !== true) {
            throw new RuntimeException('Template supervisi tidak dapat dibuka.');
        }

        $dom = new DOMDocument;
        if (! $dom->loadXML((string) $zip->getFromName('word/document.xml'), LIBXML_NONET)) {
            throw new RuntimeException('document.xml template rusak.');
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::NS_W);

        $data = $supervisi->toApi();
        $this->isiKepala($dom, $xp, $supervisi);
        $this->isiTabel($dom, $xp, $data);
        $this->isiPenutup($dom, $xp, $supervisi);

        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->addFromString('docProps/core.xml', $this->metadata((string) $zip->getFromName('docProps/core.xml')));
        $zip->close();

        return $tujuan;
    }

    private function isiKepala(DOMDocument $dom, DOMXPath $xp, Supervisi $supervisi): void
    {
        $this->tambahKeParagraf($dom, $xp, 'Penyusun Perencanaan', $supervisi->guruDinilai);
        $this->tambahKeParagraf($dom, $xp, 'Pemberi Umpan Balik', $supervisi->pemberiUmpanBalik);
        // Baris ini di template berhenti di ":" tanpa tab lanjutan, tidak seperti dua baris di atasnya.
        $this->tambahKeParagraf($dom, $xp, 'Hari/Tanggal', $this->tanggalPanjang($supervisi), awaliTab: true);
    }

    /** @param  array{butir: list<array{nomor: int, bukti: string, catatan: string}>, refleksi: list<string>}  $data */
    private function isiTabel(DOMDocument $dom, DOMXPath $xp, array $data): void
    {
        $butir = array_column($data['butir'], null, 'nomor');

        foreach ($xp->query('//w:tbl/w:tr') as $baris) {
            $sel = $xp->query('w:tc', $baris);
            $nomor = trim($sel->item(0)?->textContent ?? '');
            if (! ctype_digit($nomor)) {
                continue;
            }
            $nomor = (int) $nomor;

            if ($nomor >= 1 && $nomor <= SupervisiInstrumen::JUMLAH_BUTIR) {
                $this->isiSel($dom, $xp, $sel->item(2), 0, $butir[$nomor]['bukti']);
                $this->isiSel($dom, $xp, $sel->item(3), 0, $butir[$nomor]['catatan']);
            } elseif ($nomor > SupervisiInstrumen::JUMLAH_BUTIR) {
                // Butir 16-18: sel gabungan berisi pertanyaan, lalu paragraf kosong sebagai ruang jawaban.
                $jawaban = $data['refleksi'][$nomor - SupervisiInstrumen::JUMLAH_BUTIR - 1] ?? '';
                $this->isiSel($dom, $xp, $sel->item(1), 1, $jawaban);
            }
        }
    }

    private function isiPenutup(DOMDocument $dom, DOMXPath $xp, Supervisi $supervisi): void
    {
        foreach ($xp->query('//w:body/w:p') as $paragraf) {
            $teks = trim($paragraf->textContent);

            if (str_starts_with($teks, 'Mensupervisi,')) {
                foreach ($xp->query('w:r/w:t', $paragraf) as $t) {
                    if (trim($t->textContent) === 'Karawang,') {
                        $t->nodeValue = '';
                        $t->appendChild($dom->createTextNode(config('pguru.kota').', '.$supervisi->tanggal->locale('id')->translatedFormat('j F Y')));
                        $t->setAttributeNS(self::NS_XML, 'xml:space', 'preserve');
                    }
                }
                $this->kurangiTab($xp, $paragraf, 1);
            } elseif ($teks === self::GARIS_NAMA) {
                $t = $xp->query('w:r/w:t', $paragraf)->item(0);
                $t->nodeValue = '';
                $t->appendChild($dom->createTextNode($this->bersihkan($supervisi->pemberiUmpanBalik)));
            }
        }
    }

    /**
     * Tanggal lengkap pada "Karawang, 24 September 2026" lebih panjang dari ruang yang disiapkan
     * tab-stop template (8 tab), sehingga baris membungkus. Satu tab dibuang agar tetap satu baris.
     */
    private function kurangiTab(DOMXPath $xp, DOMElement $paragraf, int $jumlah): void
    {
        foreach ($xp->query('w:r[w:tab and not(w:t)]', $paragraf) as $run) {
            if ($jumlah-- <= 0) {
                break;
            }
            $paragraf->removeChild($run);
        }
    }

    private function tambahKeParagraf(DOMDocument $dom, DOMXPath $xp, string $label, string $nilai, bool $awaliTab = false): void
    {
        foreach ($xp->query('//w:body/w:p') as $paragraf) {
            if (str_starts_with(trim($paragraf->textContent), $label)) {
                $rPr = $xp->query('w:r[last()]/w:rPr', $paragraf)->item(0);
                $this->tambahRun($dom, $paragraf, $nilai, $rPr, $awaliTab);

                return;
            }
        }
    }

    private function isiSel(DOMDocument $dom, DOMXPath $xp, ?DOMNode $sel, int $indeksParagraf, string $teks): void
    {
        if ($sel === null || trim($teks) === '') {
            return;
        }

        $paragraf = $xp->query('w:p', $sel)->item($indeksParagraf);
        if ($paragraf === null) {
            return;
        }

        // Paragraf jawaban butir 18 di template menjorok 360 twips (sisa salin-tempel); jawaban dirata kiri
        // seperti dua butir refleksi lainnya.
        foreach ($xp->query('w:pPr/w:ind', $paragraf) as $indentasi) {
            $indentasi->parentNode->removeChild($indentasi);
        }

        // Format huruf paragraf kosong disimpan di properti "tanda paragraf" (pPr/rPr): dipakai ulang untuk run.
        $rPr = $xp->query('w:pPr/w:rPr', $paragraf)->item(0);
        $this->tambahRun($dom, $paragraf, $teks, $rPr);
    }

    private function tambahRun(DOMDocument $dom, DOMNode $paragraf, string $teks, ?DOMNode $rPr, bool $awaliTab = false): void
    {
        $run = $dom->createElementNS(self::NS_W, 'w:r');
        if ($rPr !== null) {
            $run->appendChild($rPr->cloneNode(true));
        }
        if ($awaliTab) {
            $run->appendChild($dom->createElementNS(self::NS_W, 'w:tab'));
        }

        foreach (preg_split('/\R/u', $this->bersihkan($teks)) as $i => $baris) {
            if ($i > 0) {
                $run->appendChild($dom->createElementNS(self::NS_W, 'w:br'));
            }
            $t = $dom->createElementNS(self::NS_W, 'w:t');
            $t->setAttributeNS(self::NS_XML, 'xml:space', 'preserve');
            $t->appendChild($dom->createTextNode($baris));
            $run->appendChild($t);
        }

        $paragraf->appendChild($run);
    }

    /** Membuang karakter kontrol yang tidak boleh ada di XML (isian pengguna bisa berisi apa saja). */
    private function bersihkan(string $teks): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $teks) ?? '';
    }

    private function tanggalPanjang(Supervisi $supervisi): string
    {
        return $supervisi->tanggal->locale('id')->translatedFormat('l, j F Y');
    }

    /** Template membawa nama pembuat aslinya; ganti dengan nama aplikasi. */
    private function metadata(string $xml): string
    {
        $sekarang = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $xml = preg_replace('#<dc:creator>.*?</dc:creator>#s', '<dc:creator>Perangkat Guru SMK Budi Mulia</dc:creator>', $xml);
        $xml = preg_replace('#<cp:lastModifiedBy>.*?</cp:lastModifiedBy>#s', '<cp:lastModifiedBy>Perangkat Guru SMK Budi Mulia</cp:lastModifiedBy>', $xml);
        $xml = preg_replace('#(<dcterms:created[^>]*>).*?(</dcterms:created>)#s', '${1}'.$sekarang.'${2}', $xml);

        return preg_replace('#(<dcterms:modified[^>]*>).*?(</dcterms:modified>)#s', '${1}'.$sekarang.'${2}', $xml);
    }
}
