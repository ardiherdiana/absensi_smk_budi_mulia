import * as React from "react"
import { Link, router, usePage } from "@inertiajs/react"
import {
  Bell,
  CalendarOff,
  ClipboardList,
  FileText,
  LayoutDashboard,
  LogOut,
  QrCode,
  ScanLine,
  Settings,
  User,
  Users,
} from "lucide-react"

import { useAuth } from "@/context/auth-context"
import { api } from "@/lib/api"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  useSidebar,
} from "@/components/ui/sidebar"

const NOTIFIKASI_URL = "/notifikasi"

interface NavItem {
  title: string
  url: string
  icon: React.ComponentType<{ className?: string }>
}

interface NavGroup {
  label: string
  items: NavItem[]
}

const adminNav: NavGroup[] = [
  { label: "Menu", items: [{ title: "Dashboard", url: "/dashboard", icon: LayoutDashboard }] },
  {
    label: "Absensi",
    items: [
      { title: "Scan QR", url: "/kiosk", icon: QrCode },
      { title: "Absen Briefing", url: "/briefing", icon: ScanLine },
    ],
  },
  {
    label: "Data",
    items: [
      { title: "Data Guru", url: "/data-guru", icon: Users },
      { title: "Rekap Absensi", url: "/rekap", icon: ClipboardList },
      { title: "Izin & Sakit", url: "/persetujuan", icon: FileText },
      { title: "Hari Libur", url: "/hari-libur", icon: CalendarOff },
    ],
  },
  {
    label: "Sistem",
    items: [
      { title: "Notifikasi", url: NOTIFIKASI_URL, icon: Bell },
      { title: "Profil Saya", url: "/profil", icon: User },
      { title: "Pengaturan", url: "/pengaturan", icon: Settings },
    ],
  },
]

const guruNav: NavGroup[] = [
  {
    label: "Menu",
    items: [
      { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
      { title: "QR Saya", url: "/qr", icon: QrCode },
      { title: "Riwayat Absensi", url: "/riwayat", icon: ClipboardList },
      { title: "Izin & Sakit", url: "/izin", icon: FileText },
      { title: "Profil Saya", url: "/profil", icon: User },
    ],
  },
]

// Kepsek checks in like a guru (own attendance/izin) plus a subset of
// Admin's oversight pages - but never the kiosk scan station, that stays an
// Admin-only operator concern. Split into two groups so the longer combined
// menu still scans easily: personal stuff first, school-wide oversight
// after.
const kepsekNav: NavGroup[] = [
  {
    label: "Absensi Saya",
    items: [
      { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
      { title: "QR Saya", url: "/qr", icon: QrCode },
      { title: "Riwayat Absensi", url: "/riwayat", icon: ClipboardList },
      { title: "Izin & Sakit Saya", url: "/izin", icon: FileText },
      { title: "Profil Saya", url: "/profil", icon: User },
    ],
  },
  {
    label: "Sekolah",
    items: [
      { title: "Data Guru", url: "/data-guru", icon: Users },
      { title: "Rekap Absensi", url: "/rekap", icon: ClipboardList },
      { title: "Approve Izin & Sakit", url: "/persetujuan", icon: FileText },
      { title: "Hari Libur", url: "/hari-libur", icon: CalendarOff },
      { title: "Notifikasi", url: NOTIFIKASI_URL, icon: Bell },
      { title: "Pengaturan", url: "/pengaturan", icon: Settings },
    ],
  },
]

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { user } = useAuth()
  const { url } = usePage()
  const pathname = url.split("?")[0]
  const { isMobile, setOpenMobile } = useSidebar()
  const groups = user?.role === "ADMIN" ? adminNav : user?.role === "KEPSEK" ? kepsekNav : guruNav
  const displayName = user?.role === "ADMIN" ? "Admin" : (user?.guru?.nama ?? user?.username)
  const [unreadCount, setUnreadCount] = React.useState(0)

  function logout() {
    router.post("/logout")
  }

  React.useEffect(() => {
    if (user?.role !== "ADMIN" && user?.role !== "KEPSEK") return

    let cancelled = false
    function poll() {
      api
        .get<{ count: number }>("/notifikasi/unread-count")
        .then((res) => {
          if (!cancelled) setUnreadCount(res.count)
        })
        .catch(() => {})
    }

    poll()
    const timer = setInterval(poll, 20_000)
    return () => {
      cancelled = true
      clearInterval(timer)
    }
  }, [user?.role, pathname])

  return (
    <Sidebar {...props}>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-1.5">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">Absensi Guru</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia</span>
          </div>
        </div>
      </SidebarHeader>
      <SidebarContent>
        {groups.map((group) => (
          <SidebarGroup key={group.label}>
            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {group.items.map((item) => {
                  const isExact = item.url === "/dashboard"
                  const isActive = isExact
                    ? pathname === item.url
                    : pathname.startsWith(item.url)
                  return (
                    <SidebarMenuItem key={item.title}>
                      <SidebarMenuButton
                        isActive={isActive}
                        onClick={() => isMobile && setOpenMobile(false)}
                        render={<Link href={item.url} />}
                      >
                        <item.icon />
                        {item.title}
                      </SidebarMenuButton>
                      {item.url === NOTIFIKASI_URL && unreadCount > 0 && (
                        <SidebarMenuBadge className="bg-destructive/10 text-destructive dark:bg-destructive/20">
                          {unreadCount > 99 ? "99+" : unreadCount}
                        </SidebarMenuBadge>
                      )}
                    </SidebarMenuItem>
                  )
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}
      </SidebarContent>
      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <div className="flex items-center justify-between gap-2 px-2 py-1.5 text-sm">
              <span className="truncate font-medium">{displayName}</span>
            </div>
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton onClick={logout}>
              <LogOut />
              Keluar
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
