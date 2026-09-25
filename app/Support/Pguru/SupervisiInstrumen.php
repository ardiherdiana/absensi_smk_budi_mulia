<?php

namespace App\Support\Pguru;

/**
 * Isi baku "Instrumen Observasi Implementasi dan Refleksi Perencanaan Pembelajaran" (butir 1-18),
 * diambil dari resources/pguru/format-supervisi-guru.docx. Dipakai API (bentuk form di aplikasi)
 * dan PDF, jadi teksnya cukup ditulis di satu tempat. File docx sendiri tidak dibuat ulang:
 * SupervisiDocxExporter mengisi template aslinya.
 *
 * Tiap paragraf: ['teks' => string, 'daftar' => bool]; `daftar` = butir bernomor di bawah kalimat pengantar.
 */
class SupervisiInstrumen
{
    public const JUMLAH_BUTIR = 15;

    public const JUMLAH_REFLEKSI = 3;

    /**
     * Template docx memakai spasi tunggal pada kolom "Aspek" butir ini dan spasi 1,2 pada butir lain
     * (sisa gaya salin-tempel). Dicatat agar PDF menyamai tampilan template.
     */
    public const BUTIR_SPASI_TUNGGAL = [1, 5, 6, 7, 8, 9, 15];

    /** @return list<array{judul: string, butir: list<array{nomor: int, paragraf: list<array{teks: string, daftar: bool}>}>}> */
    public static function bagian(): array
    {
        $p = fn (string $teks, bool $daftar = false) => ['teks' => $teks, 'daftar' => $daftar];

        return [
            [
                'judul' => 'Keselarasan',
                'butir' => [
                    ['nomor' => 1, 'paragraf' => [
                        $p('Tindakan implementasi perencanaan selaras dengan perencanaan pembelajaran pada:'),
                        $p('awal pembelajaran', true),
                        $p('inti pembelajaran dalam proses memahami, mengaplikasi, dan merefleksi', true),
                        $p('penutupan pembelajaran', true),
                    ]],
                    ['nomor' => 2, 'paragraf' => [$p('Upaya mencapai tujuan pembelajaran menuju pencapaian dimensi profil lulusan selaras dengan perencanaan pembelajaran')]],
                ],
            ],
            [
                'judul' => 'Implementasi Kerangka Pembelajaran',
                'butir' => [
                    ['nomor' => 3, 'paragraf' => [$p('Praktik pedagogis yang diimplementasikan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran')]],
                    ['nomor' => 4, 'paragraf' => [$p('Lingkungan belajar yang diimplementasikan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran')]],
                    ['nomor' => 5, 'paragraf' => [$p('Kemitraan pembelajaran yang diimplementasikan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran')]],
                    ['nomor' => 6, 'paragraf' => [$p('Pemanfaatan digital yang diimplementasikan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran')]],
                ],
            ],
            [
                'judul' => 'Langkah Pembelajaran',
                'butir' => [
                    ['nomor' => 7, 'paragraf' => [$p('Langkah pembelajaran yang dilakukan sesuai perencanaan dapat memfasilitasi tindakan saling MEMULIAKAN antara Guru-Murid, Murid-Guru, Murid-Murid yang tercermin dalam bahasa verbal dan nonverbal')]],
                    ['nomor' => 8, 'paragraf' => [$p('Langkah pembelajaran sudah memfasilitasi murid untuk merasakan pengalaman belajar MEMAHAMI (terlibat aktif mengonstruksi pengetahuan agar dapat memahami secara mendalam konsep atau materi dari berbagai sumber dan konteks).')]],
                    ['nomor' => 9, 'paragraf' => [$p('Langkah pembelajaran sudah memfasilitasi murid untuk merasakan pengalaman belajar MENGAPLIKASI (mengaplikasi pemahaman secara kontekstual dalam kehidupan nyata sebagai bagian dari pendalaman pengetahuan)')]],
                    ['nomor' => 10, 'paragraf' => [$p('Langkah pembelajaran sudah memfasilitasi murid untuk merasakan pengalaman belajar MEREFLEKSI (mengevaluasi dan memaknai proses serta hasil dari tindakan atau praktik nyata yang telah mereka lakukan dan menentukan tindaklanjut ke depan; serta mengelola proses belajarnya secara mandiri).')]],
                    ['nomor' => 11, 'paragraf' => [$p('Prinsip pembelajaran mendalam berupa berkesadaran, bermakna, dan/atau menggembirakan sudah tergambar pada setiap pengalaman belajar di langkah pembelajaran yang diimplementasikan.')]],
                    ['nomor' => 12, 'paragraf' => [$p('Praktik pembelajaran sudah mengakomodir pengalaman belajar yang sesuai dengan karakteristik murid (umur, tingkat perkembangan, kemampuan, bakat dan minat, gaya belajar, dll.)')]],
                ],
            ],
            [
                'judul' => 'Asesmen',
                'butir' => [
                    ['nomor' => 13, 'paragraf' => [$p('Praktik Pembelajaran sudah melakukan asesmen untuk memberikan umpan balik guna memperbaiki proses pembelajaran.')]],
                    ['nomor' => 14, 'paragraf' => [$p('Praktik Pembelajaran sudah melakukan asesmen untuk mengukur ketercapaian tujuan pembelajaran sesuai karakteristik murid')]],
                    ['nomor' => 15, 'paragraf' => [$p('Asesmen sudah dilakukan berdasarkan kriteria yang jelas dalam mengukur ketercapaian tujuan pembelajaran')]],
                ],
            ],
        ];
    }

    /** @return list<array{nomor: int, pertanyaan: string}> */
    public static function refleksi(): array
    {
        return [
            ['nomor' => 16, 'pertanyaan' => 'Pelajaran apa yang telah diperoleh dari Implementasi Perencanaan Pembelajaran yang telah dilakukan beserta faktor-faktor pendukungnya?'],
            ['nomor' => 17, 'pertanyaan' => 'Hal-hal apa saja yang pencapaiannya belum memuaskan dari Implementasi Perencanaan Pembelajaran yang telah dilakukan beserta faktor-faktor penghambatnya?'],
            ['nomor' => 18, 'pertanyaan' => 'Rencana tindak lanjut apa yang akan dibuat untuk perbaikan ke depan?'],
        ];
    }

    /**
     * Bentuk penyimpanan: daftar {nomor, bukti, catatan} untuk butir 1-15 (nomor tak dikenal dibuang,
     * yang belum ada diisi kosong), diurutkan menurut nomor.
     *
     * @param  array<int|string, mixed>  $butir
     * @return list<array{nomor: int, bukti: string, catatan: string}>
     */
    public static function lengkapiButir(array $butir): array
    {
        $byNomor = [];
        foreach ($butir as $item) {
            if (is_array($item) && isset($item['nomor'])) {
                $byNomor[(int) $item['nomor']] = $item;
            }
        }

        $hasil = [];
        for ($nomor = 1; $nomor <= self::JUMLAH_BUTIR; $nomor++) {
            $hasil[] = [
                'nomor' => $nomor,
                'bukti' => (string) ($byNomor[$nomor]['bukti'] ?? ''),
                'catatan' => (string) ($byNomor[$nomor]['catatan'] ?? ''),
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<int|string, mixed>  $refleksi  daftar jawaban untuk butir 16, 17, 18 (urut)
     * @return list<string>
     */
    public static function lengkapiRefleksi(array $refleksi): array
    {
        $refleksi = array_values($refleksi);
        $hasil = [];
        for ($i = 0; $i < self::JUMLAH_REFLEKSI; $i++) {
            $hasil[] = (string) ($refleksi[$i] ?? '');
        }

        return $hasil;
    }
}
