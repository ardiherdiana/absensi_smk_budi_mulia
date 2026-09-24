import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { toast } from "sonner"
import { Camera } from "lucide-react"

import { useAuth } from "@/context/auth-context"
import { assetUrl } from "@/lib/api"
import { initials } from "@/lib/utils"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"

const MAX_FOTO_BYTES = 2 * 1024 * 1024

export function GuruProfilPage() {
  const { user } = useAuth()
  const [uploadingPhoto, setUploadingPhoto] = React.useState(false)
  const [photoError, setPhotoError] = React.useState<string | null>(null)
  const photoInputRef = React.useRef<HTMLInputElement>(null)

  const passwordForm = useForm({
    oldPassword: "",
    newPassword: "",
    confirmPassword: "",
  })

  function handlePhotoChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ""
    if (!file) return

    if (file.size > MAX_FOTO_BYTES) {
      const message = "Ukuran foto maksimal 2MB"
      setPhotoError(message)
      toast.error(message)
      return
    }

    const body = new FormData()
    body.append("foto", file)

    setUploadingPhoto(true)
    setPhotoError(null)
    router.post("/profil/foto", body, {
      forceFormData: true,
      onSuccess: () => toast.success("Foto profil berhasil diperbarui"),
      onError: (errors) => {
        const message = Object.values(errors)[0] ?? "Gagal mengunggah foto"
        setPhotoError(message)
        toast.error(message)
      },
      onFinish: () => setUploadingPhoto(false),
    })
  }

  function handlePasswordSubmit(event: React.FormEvent) {
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
        <p className="text-sm text-muted-foreground">Kelola foto profil dan password akun Anda</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Foto Profil</CardTitle>
        </CardHeader>
        <CardContent className="flex items-center gap-4">
          <Avatar size="lg">
            {user?.guru?.fotoUrl && (
              <AvatarImage src={assetUrl(user.guru.fotoUrl)} alt={user.guru.nama} />
            )}
            <AvatarFallback>{initials(user?.guru?.nama ?? user?.username ?? "?")}</AvatarFallback>
          </Avatar>
          <div className="flex flex-col gap-2">
            <input
              ref={photoInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="hidden"
              onChange={handlePhotoChange}
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="w-fit"
              disabled={uploadingPhoto}
              onClick={() => photoInputRef.current?.click()}
            >
              <Camera /> {uploadingPhoto ? "Mengunggah..." : "Ganti Foto"}
            </Button>
            {photoError && (
              <p className="text-sm text-destructive" role="alert">
                {photoError}
              </p>
            )}
          </div>
        </CardContent>
      </Card>

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
