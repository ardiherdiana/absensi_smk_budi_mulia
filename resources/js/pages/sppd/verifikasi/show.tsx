import { formatDate, formatJam } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { SearchXIcon, ShieldAlertIcon, ShieldCheckIcon } from 'lucide-react';

interface Dokumen {
    nomor_sppd: string | null;
    pemohon: string;
    jabatan_pemohon: string | null;
    tujuan: string;
    maksud: string;
    alat_angkutan: string | null;
    pengikut: string[];
    tanggal_berangkat: string;
    jam_berangkat: string;
    tanggal_kembali: string;
    jam_kembali: string;
    penyetuju: string;
    jabatan_penyetuju: string | null;
    disetujui_at: string;
    status: string;
    sidik_jari: string | null;
}

interface Props {
    hasil: 'valid' | 'tidak_valid' | 'tidak_ditemukan';
    dokumen: Dokumen | null;
}

const BANNER = {
    valid: {
        icon: ShieldCheckIcon,
        judul: 'Tanda Tangan Elektronik Valid',
        teks: 'SPPD ini disetujui dan ditandatangani secara elektronik oleh Kepala Sekolah, dan datanya belum berubah sejak disetujui.',
        kelas: 'border-success/40 bg-success/10 text-success',
    },
    tidak_valid: {
        icon: ShieldAlertIcon,
        judul: 'Tanda Tangan Elektronik Tidak Valid',
        teks: 'Data dokumen tidak cocok dengan tanda tangan elektronik saat disetujui. Jangan gunakan dokumen ini dan laporkan ke pihak sekolah.',
        kelas: 'border-destructive/40 bg-destructive/10 text-destructive',
    },
    tidak_ditemukan: {
        icon: SearchXIcon,
        judul: 'Kode Tidak Ditemukan',
        teks: 'Kode verifikasi ini tidak terdaftar. Dokumen yang memuat QR ini tidak berasal dari sistem SPPD SMK Budi Mulia Karawang.',
        kelas: 'border-destructive/40 bg-destructive/10 text-destructive',
    },
} as const;

function Baris({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

export default function Show({ hasil, dokumen }: Props) {
    const banner = BANNER[hasil];
    const Icon = banner.icon;

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-muted/40 px-4 py-10">
            <Head title="Verifikasi TTE">
                <meta name="robots" content="noindex,nofollow" />
            </Head>

            <div className="flex items-center gap-2">
                <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-8 object-contain" />
                <div>
                    <p className="text-lg leading-tight font-semibold">SPPD SMK Budi Mulia Karawang</p>
                    <p className="text-xs leading-tight text-muted-foreground">
                        Sistem Surat Perintah Perjalanan Dinas
                    </p>
                </div>
            </div>

            <div className="w-full overflow-hidden rounded-lg border bg-card px-6 py-6 shadow-sm sm:max-w-md">
                <div className="flex flex-col gap-5">
                    <div className={`flex gap-3 rounded-lg border p-4 ${banner.kelas}`}>
                        <Icon className="mt-0.5 size-6 shrink-0" />
                        <div>
                            <h1 className="font-semibold">{banner.judul}</h1>
                            <p className="mt-1 text-sm text-foreground/80">{banner.teks}</p>
                        </div>
                    </div>

                    {dokumen && (
                        <>
                            <dl className="flex flex-col gap-3">
                                <Baris label="Nomor SPPD">{dokumen.nomor_sppd ?? 'Belum diterbitkan oleh TU'}</Baris>
                                <Baris label="Pegawai yang ditugaskan">
                                    {dokumen.pemohon}
                                    {dokumen.jabatan_pemohon && <span className="text-muted-foreground"> ({dokumen.jabatan_pemohon})</span>}
                                </Baris>
                                <Baris label="Tujuan">{dokumen.tujuan}</Baris>
                                <Baris label="Maksud">
                                    <span className="whitespace-pre-wrap">{dokumen.maksud}</span>
                                </Baris>
                                <Baris label="Alat angkutan">{dokumen.alat_angkutan ?? '-'}</Baris>
                                <Baris label="Pengikut">{dokumen.pengikut.length > 0 ? dokumen.pengikut.join(', ') : '-'}</Baris>
                                <Baris label="Berangkat">
                                    {formatDate(dokumen.tanggal_berangkat, { day: 'numeric', month: 'long', year: 'numeric' })}, {formatJam(dokumen.jam_berangkat)} WIB
                                </Baris>
                                <Baris label="Kembali">
                                    {formatDate(dokumen.tanggal_kembali, { day: 'numeric', month: 'long', year: 'numeric' })}, {formatJam(dokumen.jam_kembali)} WIB
                                </Baris>
                                <Baris label="Disetujui dan ditandatangani oleh">
                                    {dokumen.penyetuju}
                                    {dokumen.jabatan_penyetuju && <span className="text-muted-foreground"> ({dokumen.jabatan_penyetuju})</span>}
                                </Baris>
                                <Baris label="Waktu persetujuan">
                                    {formatDate(dokumen.disetujui_at, {
                                        day: 'numeric',
                                        month: 'long',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        timeZone: 'Asia/Jakarta',
                                    })}{' '}
                                    WIB
                                </Baris>
                                <Baris label="Status saat ini">{dokumen.status}</Baris>
                                {dokumen.sidik_jari && (
                                    <Baris label="Sidik jari TTE">
                                        <code className="text-xs tracking-wide">{dokumen.sidik_jari}</code>
                                    </Baris>
                                )}
                            </dl>

                            <p className="border-t pt-4 text-xs text-muted-foreground">
                                Bandingkan data di atas dengan dokumen yang Anda pegang. Jika ada yang berbeda, dokumen tersebut bukan
                                SPPD asli.
                            </p>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
