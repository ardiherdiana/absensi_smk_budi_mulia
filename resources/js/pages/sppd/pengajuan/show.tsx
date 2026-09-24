import { DatePicker } from '@/components/sppd/date-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { statusBadge } from '@/lib/pengajuan';
import { formatDate, formatJam } from '@/lib/utils';
import { type SppdPageProps } from '@/types/sppd';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { DownloadIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface UserRef {
    id: number;
    name: string;
}

interface Pencairan {
    id: number;
    jenis: string;
    jumlah: string;
    tanggal: string;
    keterangan: string | null;
    pencair: UserRef;
}

interface Pengajuan {
    id: number;
    tujuan: string;
    maksud: string;
    status: string;
    tanggal_berangkat: string;
    jam_berangkat: string;
    tanggal_kembali: string;
    jam_kembali: string;
    undangan_path: string;
    catatan_kepsek: string | null;
    tte_kode: string | null;
    alat_angkutan: string | null;
    keterangan: string | null;
    pengikuts: { id: number; name: string; jabatan: string | null }[];
    pemohon: UserRef;
    penyetuju: UserRef | null;
    sppd: { nomor_sppd: string; akun_anggaran: string | null } | null;
    pencairans: Pencairan[];
}

interface Kedatangan {
    pejabat_nama: string;
    pejabat_jabatan: string;
    tiba_tanggal: string;
    berangkat_tanggal: string;
    bukti_path: string | null;
    dikonfirmasi_at: string;
}

interface Riwayat {
    id: number;
    aksi: string;
    created_at: string;
    user: UserRef | null;
}

interface Can {
    approve: boolean;
    terbitkanSppd: boolean;
    konfirmasiKedatangan: boolean;
    cairkanUangMuka: boolean;
    selesaikan: boolean;
}

interface Props extends SppdPageProps {
    pengajuan: Pengajuan;
    riwayat: Riwayat[];
    kedatangan: Kedatangan | null;
    kedatanganDefault: { tiba_tanggal: string; berangkat_tanggal: string };
    can: Can;
}

function rupiah(v: string | number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v));
}

function DetailField({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{children}</div>
        </div>
    );
}

function ApprovalPanel({ pengajuan }: { pengajuan: Pengajuan }) {
    const { auth } = usePage<Props>().props;
    const hasSignature = Boolean(auth.user?.signature_path);
    const { data, setData, post, processing, reset } = useForm({ catatan: '' });

    const approve = () => {
        post(route('sppd.pengajuan.approve', pengajuan.id), { onSuccess: () => reset() });
    };

    const reject = () => {
        if (!data.catatan) {
            toast.error('Catatan wajib diisi untuk menolak pengajuan.');
            return;
        }
        post(route('sppd.pengajuan.reject', pengajuan.id), { onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Persetujuan Kepala Sekolah</CardTitle>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    {!hasSignature && (
                        <p className="text-sm text-muted-foreground">
                            Tanda tangan digital Anda otomatis ditempelkan pada SPPD saat menyetujui. Simpan dulu di menu{' '}
                            <Link href={route('sppd.signature.edit')} className="text-primary underline">
                                Tanda Tangan Saya
                            </Link>
                            .
                        </p>
                    )}
                    <Field>
                        <FieldLabel htmlFor="catatan">Catatan (wajib jika menolak)</FieldLabel>
                        <Textarea id="catatan" rows={2} value={data.catatan} onChange={(e) => setData('catatan', e.target.value)} />
                    </Field>
                    <div className="flex gap-2">
                        <Button type="button" disabled={processing || !hasSignature} onClick={approve}>Setujui & Tanda Tangani</Button>
                        <Button type="button" variant="destructive" disabled={processing} onClick={reject}>Tolak</Button>
                    </div>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}

function UangMukaPanel({ pengajuan }: { pengajuan: Pengajuan }) {
    const [jumlah, setJumlah] = useState('');
    const [keterangan, setKeterangan] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(route('sppd.pengajuan.cairkan-uang-muka', pengajuan.id), { jumlah, keterangan }, {
            preserveScroll: true,
            onSuccess: () => {
                setJumlah('');
                setKeterangan('');
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Cairkan Uang Muka</CardTitle>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="jumlah">Jumlah (Rp)</FieldLabel>
                        <Input id="jumlah" type="number" value={jumlah} onChange={(e) => setJumlah(e.target.value)} />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="keterangan-pencairan">Keterangan (opsional)</FieldLabel>
                        <Input id="keterangan-pencairan" value={keterangan} onChange={(e) => setKeterangan(e.target.value)} />
                    </Field>
                    <Field>
                        <Button type="button" disabled={processing || !jumlah} onClick={submit} className="w-fit">
                            {processing ? 'Memproses...' : 'Cairkan Uang Muka'}
                        </Button>
                    </Field>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}

function TerbitkanPanel({ pengajuan }: { pengajuan: Pengajuan }) {
    const { data, setData, post, processing, errors } = useForm({ akun_anggaran: '' });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Terbitkan SPPD</CardTitle>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="akun_anggaran">Akun Pembebanan Anggaran (opsional)</FieldLabel>
                        <Input
                            id="akun_anggaran"
                            placeholder="mis. 5.2.02.01 Belanja Perjalanan Dinas"
                            maxLength={100}
                            value={data.akun_anggaran}
                            onChange={(e) => setData('akun_anggaran', e.target.value)}
                        />
                        <FieldDescription>Dicetak pada baris 10 (Pembebanan Anggaran) SPPD. Instansi terisi otomatis.</FieldDescription>
                        {errors.akun_anggaran && <FieldError errors={[{ message: errors.akun_anggaran }]} />}
                    </Field>
                    <Button type="button" className="w-fit" disabled={processing} onClick={() => post(route('sppd.pengajuan.terbitkan-sppd', pengajuan.id))}>
                        {processing ? 'Memproses...' : 'Terbitkan SPPD'}
                    </Button>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}

function KedatanganPanel({ pengajuan, kedatangan, defaults }: { pengajuan: Pengajuan; kedatangan: Kedatangan | null; defaults: Props['kedatanganDefault'] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        pejabat_nama: kedatangan?.pejabat_nama ?? '',
        pejabat_jabatan: kedatangan?.pejabat_jabatan ?? '',
        tiba_tanggal: kedatangan?.tiba_tanggal ?? defaults.tiba_tanggal,
        berangkat_tanggal: kedatangan?.berangkat_tanggal ?? defaults.berangkat_tanggal,
        bukti: null as File | null,
    });

    const err = (message?: string) => (message ? [{ message }] : undefined);

    const submit: React.FormEventHandler = (e) => {
        e.preventDefault();
        post(route('sppd.pengajuan.kedatangan', pengajuan.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('bukti'),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Konfirmasi Kedatangan di Tujuan</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <p className="text-sm text-muted-foreground">
                            Isi data pejabat yang menerima Anda di tempat tujuan. Data ini dicetak pada kolom &quot;Kepala&quot; di sisi belakang SPPD.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="pejabat_nama">Nama Pejabat Penerima</FieldLabel>
                                <Input id="pejabat_nama" value={data.pejabat_nama} onChange={(e) => setData('pejabat_nama', e.target.value)} />
                                <FieldError errors={err(errors.pejabat_nama)} />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="pejabat_jabatan">Jabatan Pejabat</FieldLabel>
                                <Input id="pejabat_jabatan" value={data.pejabat_jabatan} onChange={(e) => setData('pejabat_jabatan', e.target.value)} />
                                <FieldError errors={err(errors.pejabat_jabatan)} />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="tiba_tanggal">Tanggal Tiba</FieldLabel>
                                <DatePicker id="tiba_tanggal" value={data.tiba_tanggal} onChange={(v) => setData('tiba_tanggal', v)} />
                                <FieldError errors={err(errors.tiba_tanggal)} />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="berangkat_tanggal">Tanggal Berangkat dari Tujuan</FieldLabel>
                                <DatePicker id="berangkat_tanggal" value={data.berangkat_tanggal} onChange={(v) => setData('berangkat_tanggal', v)} />
                                <FieldError errors={err(errors.berangkat_tanggal)} />
                            </Field>
                        </div>
                        <Field>
                            <FieldLabel htmlFor="bukti">Bukti Kedatangan (opsional)</FieldLabel>
                            <Input id="bukti" type="file" accept="application/pdf,image/*" onChange={(e) => setData('bukti', e.target.files?.[0] ?? null)} />
                            <FieldDescription>Foto/scan surat tugas yang distempel, PDF atau gambar maks. 5 MB.</FieldDescription>
                            <FieldError errors={err(errors.bukti)} />
                        </Field>
                        <Button type="submit" className="w-fit" disabled={processing}>
                            {processing ? 'Menyimpan...' : kedatangan ? 'Perbarui Konfirmasi' : 'Konfirmasi Kedatangan'}
                        </Button>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

export default function Show() {
    const { pengajuan, riwayat, can, kedatangan, kedatanganDefault } = usePage<Props>().props;
    const badge = statusBadge(pengajuan.status);

    return (
        <>
            <Head title={`Pengajuan #${pengajuan.id}`} />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Pengajuan SPPD #{pengajuan.id}</h1>
                        <p className="text-sm text-muted-foreground">Diajukan oleh {pengajuan.pemohon.name}</p>
                    </div>
                    <Badge variant={badge.variant}>{badge.label}</Badge>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Detail Pengajuan</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <DetailField label="Pemohon">
                                        {pengajuan.pemohon.name}
                                    </DetailField>
                                    <DetailField label="Tujuan">{pengajuan.tujuan}</DetailField>
                                    <DetailField label="Berangkat">
                                        {formatDate(pengajuan.tanggal_berangkat)}, {formatJam(pengajuan.jam_berangkat)}
                                    </DetailField>
                                    <DetailField label="Kembali">
                                        {formatDate(pengajuan.tanggal_kembali)}, {formatJam(pengajuan.jam_kembali)}
                                    </DetailField>
                                </div>
                                <DetailField label="Maksud">
                                    <span className="whitespace-pre-wrap">{pengajuan.maksud}</span>
                                </DetailField>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <DetailField label="Alat Angkutan">{pengajuan.alat_angkutan ?? '-'}</DetailField>
                                    <DetailField label="Keterangan Lain-lain">{pengajuan.keterangan ?? '-'}</DetailField>
                                </div>
                                <DetailField label="Pengikut">
                                    {pengajuan.pengikuts.length === 0
                                        ? '-'
                                        : pengajuan.pengikuts.map((p) => (p.jabatan ? `${p.name} (${p.jabatan})` : p.name)).join(', ')}
                                </DetailField>
                                <DetailField label="Surat Undangan">
                                    <a href={`/uploads/${pengajuan.undangan_path}`} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                        Lihat undangan
                                    </a>
                                </DetailField>
                                {pengajuan.catatan_kepsek && <DetailField label="Catatan Kepala Sekolah">{pengajuan.catatan_kepsek}</DetailField>}
                                {pengajuan.penyetuju && <DetailField label="Disetujui oleh">{pengajuan.penyetuju.name}</DetailField>}
                                {pengajuan.tte_kode && (
                                    <DetailField label="Tanda Tangan Elektronik (TTE)">
                                        <a
                                            href={route('sppd.tte.verifikasi', pengajuan.tte_kode)}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            Buka halaman verifikasi
                                        </a>
                                    </DetailField>
                                )}

                                {pengajuan.sppd && (
                                    <div className="flex flex-wrap gap-2 border-t pt-4">
                                        <Button variant="outline" nativeButton={false} render={<a href={route('sppd.pengajuan.sppd-pdf', pengajuan.id)} />}>
                                            <DownloadIcon /> Download SPPD ({pengajuan.sppd.nomor_sppd})
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {can.approve && <ApprovalPanel pengajuan={pengajuan} />}

                        {can.terbitkanSppd && <TerbitkanPanel pengajuan={pengajuan} />}

                        {can.konfirmasiKedatangan && (
                            <KedatanganPanel pengajuan={pengajuan} kedatangan={kedatangan} defaults={kedatanganDefault} />
                        )}

                        {kedatangan && !can.konfirmasiKedatangan && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Kedatangan di Tujuan</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3 sm:grid-cols-2">
                                    <DetailField label="Pejabat Penerima">
                                        {kedatangan.pejabat_nama} ({kedatangan.pejabat_jabatan})
                                    </DetailField>
                                    <DetailField label="Tiba">{formatDate(kedatangan.tiba_tanggal)}</DetailField>
                                    <DetailField label="Berangkat dari Tujuan">{formatDate(kedatangan.berangkat_tanggal)}</DetailField>
                                    {kedatangan.bukti_path && (
                                        <DetailField label="Bukti">
                                            <a href={`/uploads/${kedatangan.bukti_path}`} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                                Lihat bukti
                                            </a>
                                        </DetailField>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {can.cairkanUangMuka && <UangMukaPanel pengajuan={pengajuan} />}

                        {can.selesaikan && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Selesaikan Perjalanan Dinas</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <p className="text-sm text-muted-foreground">
                                        Tandai perjalanan dinas ini selesai setelah pemohon kembali. Dokumen akan diarsipkan.
                                    </p>
                                    <Button
                                        type="button"
                                        className="w-fit"
                                        onClick={() => {
                                            if (window.confirm('Tandai perjalanan dinas ini selesai dan arsipkan?')) {
                                                router.post(route('sppd.pengajuan.selesai', pengajuan.id));
                                            }
                                        }}
                                    >
                                        Tandai Selesai & Arsipkan
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {pengajuan.pencairans.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Riwayat Pencairan Dana</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto rounded-lg border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-10">No</TableHead>
                                                    <TableHead>Jenis</TableHead>
                                                    <TableHead>Jumlah</TableHead>
                                                    <TableHead>Tanggal</TableHead>
                                                    <TableHead>Oleh</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {pengajuan.pencairans.map((p, index) => (
                                                    <TableRow key={p.id}>
                                                        <TableCell className="text-muted-foreground">{index + 1}</TableCell>
                                                        <TableCell className="capitalize">{p.jenis.replace('_', ' ')}</TableCell>
                                                        <TableCell>{rupiah(p.jumlah)}</TableCell>
                                                        <TableCell>{formatDate(p.tanggal)}</TableCell>
                                                        <TableCell>{p.pencair.name}</TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Riwayat & Audit Trail</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="flex flex-col gap-4 border-l pl-4">
                                    {riwayat.map((r) => (
                                        <li key={r.id} className="relative">
                                            <span className="absolute top-1 -left-[21px] size-2 rounded-full bg-primary" />
                                            <p className="text-sm">{r.aksi}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {r.user?.name ?? 'Sistem'} &middot; {new Date(r.created_at).toLocaleString('id-ID')}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
