import { AdminProfilPage } from '@/pages/admin/profil-page';
import { GuruProfilPage } from '@/pages/guru/profil-page';
import { usePage } from '@inertiajs/react';

/** Halaman profil di dalam modul SPPD: memakai isi yang sama dengan profil absensi, tetapi tampil dengan sidebar SPPD. */
export default function Profil() {
    const { punyaGuru } = usePage<{ punyaGuru: boolean }>().props;

    return punyaGuru ? <GuruProfilPage /> : <AdminProfilPage />;
}
