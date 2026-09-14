import * as React from "react"
import { Link, router } from "@inertiajs/react"
import { Bell, CheckCircle2, ClipboardList, Clock, FileText, QrCode, Users, XCircle } from "lucide-react"

import { cn, initials } from "@/lib/utils"
import { api, assetUrl } from "@/lib/api"
import type { Guru, LeaveRequest, Notification, RekapRow } from "@/lib/types"
import { statusLabel } from "@/lib/attendance-format"
import { NOTIFICATION_TYPE_META, formatNotificationWaktu, notificationTargetPath } from "@/lib/notification"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardAction, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { AttendanceTrendChart, type TrendPoint } from "@/components/charts/attendance-trend-chart"

const RECENT_NOTIFICATIONS_LIMIT = 5
const TREND_DAYS = 30

const STATUS_CARDS = [
  { status: "HADIR", icon: CheckCircle2, tint: "bg-emerald-500/5 text-emerald-600" },
  { status: "TELAT", icon: Clock, tint: "bg-amber-500/5 text-amber-600" },
  { status: "ALPA", icon: XCircle, tint: "bg-destructive/5 text-destructive" },
] as const

function GuruChip({ nama, fotoUrl }: { nama: string; fotoUrl?: string | null }) {
  return (
    <div className="flex w-14 flex-col items-center gap-1 text-center">
      <Avatar>
        {fotoUrl && <AvatarImage src={assetUrl(fotoUrl)} alt={nama} />}
        <AvatarFallback>{initials(nama)}</AvatarFallback>
      </Avatar>
      <span className="line-clamp-2 text-[11px] leading-tight text-muted-foreground">{nama}</span>
    </div>
  )
}

interface Props {
  guruList: Guru[]
  today: RekapRow[]
  pendingIzin: LeaveRequest[]
  notifications: Notification[]
  trend: TrendPoint[]
}

export function AdminDashboardPage({ guruList, today, pendingIzin, notifications: initialNotifications, trend }: Props) {
  const [notifications, setNotifications] = React.useState(initialNotifications)

  async function handleNotificationClick(notification: Notification) {
    if (!notification.isRead) {
      setNotifications((rows) => rows.map((r) => (r.id === notification.id ? { ...r, isRead: true } : r)))
      try {
        await api.patch(`/notifikasi/${notification.id}/read`)
      } catch {
        // optimistic update; navigation already proceeds regardless
      }
    }
    router.visit(notificationTargetPath(notification))
  }

  const guruById = React.useMemo(() => new Map(guruList.map((g) => [g.id, g])), [guruList])

  const aktifCount = guruList.filter((g) => g.aktif).length
  const hadirCount = today.filter((r) => r.status === "HADIR" || r.status === "TELAT").length
  const telatCount = today.filter((r) => r.status === "TELAT").length

  const stats = [
    { label: "Guru Aktif", value: aktifCount, icon: Users },
    { label: "Hadir Hari Ini", value: hadirCount, icon: ClipboardList },
    { label: "Telat Hari Ini", value: telatCount, icon: ClipboardList },
    { label: "Izin Menunggu", value: pendingIzin.length, icon: FileText },
  ]

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-xl font-semibold">Dashboard</h1>
        <p className="text-sm text-muted-foreground">Ringkasan absensi hari ini</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.label}>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                {stat.label}
              </CardTitle>
              <CardAction>
                <stat.icon className="size-4 text-muted-foreground" />
              </CardAction>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-semibold">{stat.value}</div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Status Kehadiran Hari Ini</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-3">
            {STATUS_CARDS.map(({ status, icon: Icon, tint }) => {
              const rows = today.filter((r) => r.status === status)
              return (
                <div key={status} className={cn("rounded-lg border p-4", tint)}>
                  <div className="flex items-center gap-2 text-sm font-medium">
                    <Icon className="size-4" />
                    {statusLabel(status)}
                    <span className="font-normal text-muted-foreground">({rows.length})</span>
                  </div>
                  {rows.length === 0 ? (
                    <p className="mt-3 text-xs text-muted-foreground">Tidak ada</p>
                  ) : (
                    <div className="mt-3 grid grid-cols-[repeat(auto-fill,minmax(3.5rem,1fr))] justify-items-center gap-3">
                      {rows.map((row) => (
                        <GuruChip
                          key={row.guruId}
                          nama={row.nama}
                          fotoUrl={guruById.get(row.guruId)?.fotoUrl}
                        />
                      ))}
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Notifikasi Terbaru</CardTitle>
          <CardAction>
            <Button variant="outline" size="sm" render={<Link href="/notifikasi" />}>
              Lihat Selengkapnya
            </Button>
          </CardAction>
        </CardHeader>
        <CardContent className="divide-y p-0">
          {notifications.length === 0 ? (
            <div className="flex flex-col items-center gap-2 p-8 text-center text-muted-foreground">
              <Bell className="size-6" />
              <p className="text-sm">Belum ada notifikasi</p>
            </div>
          ) : (
            notifications.slice(0, RECENT_NOTIFICATIONS_LIMIT).map((notification) => {
              const meta = NOTIFICATION_TYPE_META[notification.type]
              const Icon = meta.icon
              return (
                <button
                  key={notification.id}
                  onClick={() => handleNotificationClick(notification)}
                  className={cn(
                    "flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted",
                    !notification.isRead && "bg-accent/40"
                  )}
                >
                  <div
                    className={cn(
                      "mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full",
                      meta.iconClass
                    )}
                  >
                    <Icon className="size-4" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium">{notification.judul}</span>
                      {!notification.isRead && (
                        <span className="size-1.5 rounded-full bg-primary" aria-hidden />
                      )}
                    </div>
                    <p className="text-sm text-muted-foreground">{notification.pesan}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      {formatNotificationWaktu(notification.createdAt)}
                    </p>
                  </div>
                </button>
              )
            })
          )}
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">
              Tren Kehadiran ({TREND_DAYS} Hari Terakhir)
            </CardTitle>
          </CardHeader>
          <CardContent>
            <AttendanceTrendChart data={trend} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Aksi Cepat</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-3">
            <Button render={<Link href="/kiosk" />}>
              <QrCode /> Buka Layar Kiosk
            </Button>
            <Button variant="outline" render={<Link href="/data-guru" />}>
              <Users /> Kelola Data Guru
            </Button>
            <Button variant="outline" render={<Link href="/rekap" />}>
              <ClipboardList /> Lihat Rekap Absensi
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
