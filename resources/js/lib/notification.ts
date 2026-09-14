import { FileText, LogIn, LogOut } from "lucide-react"

import type { Notification, NotificationType } from "@/lib/types"

export const NOTIFICATION_TYPE_META: Record<
  NotificationType,
  { icon: typeof LogIn; iconClass: string; label: string }
> = {
  CHECKIN: { icon: LogIn, iconClass: "bg-emerald-500/10 text-emerald-600", label: "Absen Masuk" },
  CHECKOUT: { icon: LogOut, iconClass: "bg-sky-500/10 text-sky-600", label: "Absen Pulang" },
  LEAVE_REQUEST: { icon: FileText, iconClass: "bg-amber-500/10 text-amber-600", label: "Izin/Sakit" },
}

/** Where clicking a notification should navigate to - there's no direct FK to
 * the specific attendance row or leave request, so this lands the admin on
 * the relevant page filtered to that guru/date rather than a single record. */
export function notificationTargetPath(notification: Notification): string {
  if (notification.type === "LEAVE_REQUEST") {
    return "/persetujuan?status=all"
  }
  const date = notification.createdAt.slice(0, 10)
  const params = new URLSearchParams({ from: date, to: date })
  if (notification.guruId) params.set("guruId", notification.guruId)
  return `/rekap?${params.toString()}`
}

export function formatNotificationWaktu(iso: string): string {
  return new Date(iso).toLocaleString("id-ID", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  })
}
