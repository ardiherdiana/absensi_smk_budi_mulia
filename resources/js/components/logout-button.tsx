import { router } from "@inertiajs/react"
import { LogOut } from "lucide-react"

import { Button } from "@/components/ui/button"

/** Tombol keluar di halaman profil: tombol profil di Menu Utama membuka halaman
 * ini, jadi keluar dari akun dilakukan dari sini. */
export function LogoutButton() {
  return (
    <Button type="button" variant="outline" size="lg" className="w-full" onClick={() => router.post("/logout")}>
      <LogOut /> Keluar
    </Button>
  )
}
