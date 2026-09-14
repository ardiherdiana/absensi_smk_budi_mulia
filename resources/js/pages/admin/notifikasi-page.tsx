import * as React from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"
import { Bell, CheckCheck } from "lucide-react"

import { api, ApiError } from "@/lib/api"
import type { Notification, NotificationType } from "@/lib/types"
import { NOTIFICATION_TYPE_META, formatNotificationWaktu, notificationTargetPath } from "@/lib/notification"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { DataPagination } from "@/components/data-pagination"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { cn } from "@/lib/utils"

type FilterType = "ALL" | NotificationType

const FILTERS: { value: FilterType; label: string }[] = [
  { value: "ALL", label: "Semua" },
  { value: "CHECKIN", label: "Absen Masuk" },
  { value: "CHECKOUT", label: "Absen Pulang" },
  { value: "LEAVE_REQUEST", label: "Izin/Sakit" },
]

export function NotifikasiPage({ list: initialList }: { list: Notification[] }) {
  const [list, setList] = React.useState(initialList)
  const [filter, setFilter] = React.useState<FilterType>("ALL")
  const [markingAll, setMarkingAll] = React.useState(false)
  const [page, setPage] = React.useState(1)
  const [pageSize, setPageSize] = React.useState(50)

  React.useEffect(() => {
    setList(initialList)
  }, [initialList])

  React.useEffect(() => {
    setPage(1)
  }, [filter])

  const unreadCount = list.filter((n) => !n.isRead).length
  const filtered = list.filter((n) => filter === "ALL" || n.type === filter)
  const paginated = filtered.slice((page - 1) * pageSize, page * pageSize)

  function countFor(type: FilterType) {
    return type === "ALL" ? list.length : list.filter((n) => n.type === type).length
  }

  async function handleMarkRead(notification: Notification) {
    if (notification.isRead) return
    setList((rows) => rows.map((r) => (r.id === notification.id ? { ...r, isRead: true } : r)))
    try {
      await api.patch(`/notifikasi/${notification.id}/read`)
    } catch {
      // optimistic update; navigation already proceeds regardless
    }
  }

  async function handleMarkAll() {
    setMarkingAll(true)
    try {
      await api.post("/notifikasi/read-all")
      setList((rows) => rows.map((r) => ({ ...r, isRead: true })))
      toast.success("Semua notifikasi ditandai dibaca")
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal menandai notifikasi")
    } finally {
      setMarkingAll(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Notifikasi</h1>
          <p className="text-sm text-muted-foreground">
            Riwayat aktivitas absen masuk, pulang, dan pengajuan izin/sakit
          </p>
        </div>
        <Button variant="outline" disabled={unreadCount === 0 || markingAll} onClick={handleMarkAll}>
          <CheckCheck /> Tandai semua dibaca
        </Button>
      </div>

      <Tabs value={filter} onValueChange={(v) => setFilter(v as FilterType)}>
        <TabsList>
          {FILTERS.map((f) => (
            <TabsTrigger key={f.value} value={f.value}>
              {f.label}
              <Badge variant="secondary" className="ml-1">
                {countFor(f.value)}
              </Badge>
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      <Card>
        <CardContent className="divide-y p-0">
          {filtered.length === 0 ? (
            <div className="flex flex-col items-center gap-2 p-10 text-center text-muted-foreground">
              <Bell className="size-8" />
              <p className="text-sm">Belum ada notifikasi</p>
            </div>
          ) : (
            paginated.map((notification) => {
              const meta = NOTIFICATION_TYPE_META[notification.type]
              const Icon = meta.icon
              return (
                <button
                  key={notification.id}
                  onClick={() => {
                    handleMarkRead(notification)
                    router.visit(notificationTargetPath(notification))
                  }}
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

      {filtered.length > 0 && (
        <DataPagination
          page={page}
          pageSize={pageSize}
          total={filtered.length}
          onPageChange={setPage}
          onPageSizeChange={(size) => {
            setPageSize(size)
            setPage(1)
          }}
        />
      )}
    </div>
  )
}
