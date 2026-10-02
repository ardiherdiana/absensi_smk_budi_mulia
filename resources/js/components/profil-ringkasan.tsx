import { usePage } from "@inertiajs/react"
import { BadgeCheck, CalendarDays, Phone, UserRound } from "lucide-react"

import { assetUrl } from "@/lib/api"
import { initials } from "@/lib/utils"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useAuth } from "@/context/auth-context"

interface ProfilProps {
  akun: {
    username: string
    nama: string | null
    jabatan: string | null
    peranAbsensi: "ADMIN" | "GURU" | "KEPSEK" | null
    peranSppd: string[]
    bergabung: string | null
  }
  guru: { nama: string; mapel: string | null; noHp: string | null; aktif: boolean } | null
  kehadiranBulanIni: Record<"HADIR" | "TELAT" | "IZIN" | "SAKIT" | "ALPA", number> | null
  [key: string]: unknown
}

const PERAN_ABSENSI: Record<string, string> = {
  ADMIN: "Administrator",
  GURU: "Guru",
  KEPSEK: "Kepala Sekolah",
}

const PERAN_SPPD: Record<string, string> = {
  pemohon: "Pemohon SPPD",
  kepala_sekolah: "Kepala Sekolah (SPPD)",
  tu: "Tata Usaha (SPPD)",
  bendahara: "Bendahara (SPPD)",
}

const KEHADIRAN: { key: keyof NonNullable<ProfilProps["kehadiranBulanIni"]>; label: string }[] = [
  { key: "HADIR", label: "Hadir" },
  { key: "TELAT", label: "Telat" },
  { key: "IZIN", label: "Izin" },
  { key: "SAKIT", label: "Sakit" },
  { key: "ALPA", label: "Alpa" },
]

function Baris({ icon: Icon, label, children }: { icon: React.ComponentType<{ className?: string }>; label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-start gap-3 text-sm">
      <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
      <div className="min-w-0">
        <div className="text-xs text-muted-foreground">{label}</div>
        <div className="break-words">{children}</div>
      </div>
    </div>
  )
}

/** Kartu ringkasan di halaman profil: data akun, data guru, dan kehadiran bulan berjalan.
 * Dipakai bersama oleh profil absensi dan profil di modul SPPD. */
export function ProfilRingkasan() {
  const { user } = useAuth()
  const { akun, guru, kehadiranBulanIni } = usePage<ProfilProps>().props

  const nama = guru?.nama ?? akun.nama ?? akun.username
  const bulan = new Date().toLocaleDateString("id-ID", { month: "long", year: "numeric" })
  const bergabung = akun.bergabung
    ? new Date(akun.bergabung).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" })
    : null

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Informasi Akun</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex items-center gap-4">
            <Avatar size="lg">
              {user?.guru?.fotoUrl && <AvatarImage src={assetUrl(user.guru.fotoUrl)} alt={nama} />}
              <AvatarFallback>{initials(nama)}</AvatarFallback>
            </Avatar>
            <div className="min-w-0">
              <p className="truncate text-base font-medium">{nama}</p>
              <p className="truncate text-sm text-muted-foreground">@{akun.username}</p>
              <div className="mt-1.5 flex flex-wrap gap-1.5">
                {akun.peranAbsensi && <Badge variant="secondary">{PERAN_ABSENSI[akun.peranAbsensi]}</Badge>}
                {akun.peranSppd.map((peran) => (
                  <Badge key={peran} variant="outline">
                    {PERAN_SPPD[peran] ?? peran}
                  </Badge>
                ))}
              </div>
            </div>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <Baris icon={UserRound} label="Nama pengguna">
              {akun.username}
            </Baris>
            {akun.jabatan && (
              <Baris icon={BadgeCheck} label="Jabatan">
                {akun.jabatan}
              </Baris>
            )}
            {guru?.mapel && (
              <Baris icon={BadgeCheck} label="Mata pelajaran">
                {guru.mapel}
              </Baris>
            )}
            {guru?.noHp && (
              <Baris icon={Phone} label="No. HP">
                {guru.noHp}
              </Baris>
            )}
            {bergabung && (
              <Baris icon={CalendarDays} label="Akun dibuat">
                {bergabung}
              </Baris>
            )}
          </div>
        </CardContent>
      </Card>

      {kehadiranBulanIni && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Kehadiran {bulan}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-5 gap-2 text-center">
              {KEHADIRAN.map(({ key, label }) => (
                <div key={key} className="rounded-lg border p-2">
                  <div className="text-lg font-semibold tabular-nums">{kehadiranBulanIni[key]}</div>
                  <div className="text-xs text-muted-foreground">{label}</div>
                </div>
              ))}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">Dihitung dari awal bulan sampai hari ini, hanya hari sekolah aktif.</p>
          </CardContent>
        </Card>
      )}
    </>
  )
}
