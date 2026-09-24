export type Role = "ADMIN" | "GURU" | "KEPSEK"

export interface AuthUser {
  id: string
  username: string
  role: Role
  guru: { id: string; nama: string; fotoUrl: string | null } | null
  // Kolom profil modul SPPD — sama-sama ada di tabel `users` bersama, dibagikan lewat
  // HandleInertiaRequests untuk kedua modul.
  name: string | null
  jabatan: string | null
  signature_path: string | null
  is_active: boolean
}

export interface Guru {
  id: string
  nama: string
  noHp: string | null
  mapel: string | null
  fotoUrl: string | null
  qrToken: string
  aktif: boolean
  createdAt: string
  user: { username: string; role: Role }
}

export interface Attendance {
  id: string
  guruId: string
  tanggal: string
  jamMasuk: string | null
  jamPulang: string | null
  statusMasuk: StatusKehadiran | null
  statusPulang: StatusKehadiran | null
  catatan: string | null
}

export type StatusKehadiran = "HADIR" | "TELAT" | "ALPA" | "IZIN" | "SAKIT"

export interface RekapRow {
  guruId: string
  nama: string
  tanggal: string
  jamMasuk: string | null
  jamPulang: string | null
  status: StatusKehadiran | null
  catatan: string | null
}

export interface GuruAttendanceDetail {
  guruId: string
  nama: string
  fotoUrl: string | null
  streak: number
  days: RekapRow[]
}

export type JenisIzin = "IZIN" | "SAKIT"
export type StatusIzin = "PENDING" | "APPROVED" | "REJECTED"

export interface LeaveRequest {
  id: string
  guruId: string
  jenis: JenisIzin
  tanggalMulai: string
  tanggalSelesai: string
  alasan: string
  lampiranUrl: string | null
  status: StatusIzin
  reviewedBy: string | null
  reviewedAt: string | null
  createdAt: string
  guru?: { nama: string }
}

export type NotificationType = "CHECKIN" | "CHECKOUT" | "LEAVE_REQUEST"

export interface Notification {
  id: string
  type: NotificationType
  judul: string
  pesan: string
  guruId: string | null
  isRead: boolean
  createdAt: string
  guru?: { nama: string } | null
}

export interface Holiday {
  id: string
  tanggal: string
  keterangan: string
}

export interface Settings {
  id: number
  namaSekolah: string
}

/** One weekly recurring schedule row - `hari` follows JS's day-of-week
 * convention (0=Minggu ... 6=Sabtu). */
export interface JadwalHari {
  hari: number
  aktif: boolean
  /** Earliest time absen masuk is allowed - separate from `jamMasuk`, which
   * stays only the HADIR/TELAT reference point (compared against
   * `batasTelat`). */
  jamMulaiAbsen: string
  jamMasuk: string
  batasTelat: string
  jamPulang: string
  /** null = no briefing scheduled this day. */
  jamBriefing: string | null
  /** null = no closing gate - absen briefing stays open the rest of the
   * day once jamBriefing opens. */
  jamSelesaiBriefing: string | null
}

export interface CheckinResult {
  type: "MASUK" | "PULANG"
  statusMasuk?: StatusKehadiran
  jam: string
  attendance: Attendance
  nama: string
  fotoUrl: string | null
}

export interface BriefingCheckinResult {
  nama: string
  fotoUrl: string | null
  waktu: string
}

export interface BriefingRekapRow {
  guruId: string
  nama: string
  tanggal: string
  waktu: string | null
  status: StatusKehadiran | null
}
