import type { FormEvent } from "react"
import { useForm } from "@inertiajs/react"
import { toast } from "sonner"

import { useAuth } from "@/context/auth-context"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"

export function AdminProfilPage() {
  const { user } = useAuth()

  const passwordForm = useForm({
    oldPassword: "",
    newPassword: "",
    confirmPassword: "",
  })

  function handlePasswordSubmit(event: FormEvent) {
    event.preventDefault()

    if (passwordForm.data.newPassword !== passwordForm.data.confirmPassword) {
      passwordForm.setError("confirmPassword", "Konfirmasi password baru tidak cocok")
      return
    }

    passwordForm.post("/profil/password", {
      onSuccess: () => {
        passwordForm.reset()
        toast.success("Password berhasil diganti")
      },
    })
  }

  return (
    <div className="flex max-w-lg flex-col gap-6">
      <div>
        <h1 className="text-xl font-semibold">Profil Saya</h1>
        <p className="text-sm text-muted-foreground">
          Kelola password akun {user?.username}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Ganti Password</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handlePasswordSubmit}>
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="oldPassword">Password Lama</FieldLabel>
                <Input
                  id="oldPassword"
                  type="password"
                  required
                  autoComplete="current-password"
                  value={passwordForm.data.oldPassword}
                  onChange={(e) => passwordForm.setData("oldPassword", e.target.value)}
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="newPassword">Password Baru</FieldLabel>
                <Input
                  id="newPassword"
                  type="password"
                  required
                  minLength={6}
                  autoComplete="new-password"
                  value={passwordForm.data.newPassword}
                  onChange={(e) => passwordForm.setData("newPassword", e.target.value)}
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="confirmPassword">Konfirmasi Password Baru</FieldLabel>
                <Input
                  id="confirmPassword"
                  type="password"
                  required
                  minLength={6}
                  autoComplete="new-password"
                  value={passwordForm.data.confirmPassword}
                  onChange={(e) => passwordForm.setData("confirmPassword", e.target.value)}
                />
              </Field>
              {(passwordForm.errors.oldPassword ??
                passwordForm.errors.newPassword ??
                passwordForm.errors.confirmPassword) && (
                <p className="text-sm text-destructive" role="alert">
                  {passwordForm.errors.oldPassword ??
                    passwordForm.errors.newPassword ??
                    passwordForm.errors.confirmPassword}
                </p>
              )}
              <Field>
                <Button type="submit" disabled={passwordForm.processing} className="w-fit">
                  {passwordForm.processing ? "Menyimpan..." : "Simpan Password"}
                </Button>
              </Field>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
