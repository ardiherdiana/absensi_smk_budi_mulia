import { Link, router } from "@inertiajs/react"
import { ArrowRight, CalendarCheckIcon, LogOut, SendIcon } from "lucide-react"

import { useAuth } from "@/context/auth-context"
import { ThemeToggle } from "@/components/theme-toggle"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"

interface ModuleCard {
  title: string
  description: string
  icon: React.ComponentType<{ className?: string }>
  href: string
  badge?: string
}

const MODULES: ModuleCard[] = [
  {
    title: "Absen",
    description: "Rekap kehadiran, jadwal, dan izin guru",
    icon: CalendarCheckIcon,
    href: "/absen/dashboard",
  },
  {
    title: "SPPD",
    description: "Pengajuan perjalanan dinas, persetujuan, dan pencairan",
    icon: SendIcon,
    href: route("sppd.dashboard", undefined, false),
    badge: "Sedang dalam pengembangan",
  },
]

export function ModulePickerPage() {
  const { user } = useAuth()
  const displayName = user?.role === "ADMIN" ? "Admin" : (user?.guru?.nama ?? user?.username)

  function logout() {
    router.post("/logout")
  }

  return (
    <div className="flex min-h-svh flex-col">
      <header className="flex h-14 shrink-0 items-center justify-between border-b px-4 md:px-6">
        <div className="flex items-center gap-2">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">SMK Budi Mulia</span>
            <span className="text-xs text-muted-foreground">Pilih modul</span>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <span className="hidden text-sm text-muted-foreground sm:inline">{displayName}</span>
          <ThemeToggle />
          <Button variant="ghost" size="sm" onClick={logout}>
            <LogOut />
            Keluar
          </Button>
        </div>
      </header>

      <div className="grid flex-1 grid-cols-1 sm:grid-cols-2">
        {MODULES.map((module) => (
          <Link
            key={module.title}
            href={module.href}
            className="group relative flex flex-col items-center justify-center gap-4 overflow-hidden border-b p-10 text-center transition-colors duration-300 last:border-b-0 hover:bg-muted sm:border-r sm:border-b-0 sm:last:border-r-0"
          >
            <span className="absolute top-0 left-0 h-0.5 w-0 bg-foreground transition-all duration-500 ease-out group-hover:w-full" />

            <div className="flex size-14 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover:scale-110 group-hover:bg-primary group-hover:text-primary-foreground">
              <module.icon className="size-6" />
            </div>
            <div className="space-y-1.5">
              <h2 className="text-lg font-semibold">{module.title}</h2>
              {module.badge && <Badge variant="warning">{module.badge}</Badge>}
              <p className="text-sm text-muted-foreground">{module.description}</p>
            </div>
            <span className="flex items-center gap-2 text-sm font-medium text-muted-foreground transition-all duration-300 group-hover:gap-3 group-hover:text-foreground">
              Akses Modul <ArrowRight className="size-4 transition-transform duration-300 group-hover:translate-x-1" />
            </span>
          </Link>
        ))}
      </div>
    </div>
  )
}
