import * as React from "react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"

export interface ConfirmOptions {
  title: string
  description?: React.ReactNode
  confirmLabel?: string
  cancelLabel?: string
  /** Tombol konfirmasi berwarna merah, untuk tindakan yang menghapus atau tidak bisa dibatalkan. */
  destructive?: boolean
}

/** Pengganti `window.confirm` dengan AlertDialog shadcn. Pakai `await confirm({...})` di
 * handler (bernilai true bila pengguna menekan konfirmasi) dan render `confirmDialog`
 * sekali di JSX komponen. */
export function useConfirm() {
  const [open, setOpen] = React.useState(false)
  // Opsi terakhir dipertahankan setelah dialog tertutup supaya teks tidak hilang saat animasi keluar.
  const [options, setOptions] = React.useState<ConfirmOptions>({ title: "" })
  const resolveRef = React.useRef<((result: boolean) => void) | null>(null)

  const confirm = React.useCallback((next: ConfirmOptions) => {
    resolveRef.current?.(false)
    setOptions(next)
    setOpen(true)
    return new Promise<boolean>((resolve) => {
      resolveRef.current = resolve
    })
  }, [])

  function settle(result: boolean) {
    resolveRef.current?.(result)
    resolveRef.current = null
    setOpen(false)
  }

  const confirmDialog = (
    <AlertDialog open={open} onOpenChange={(next) => !next && settle(false)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{options.title}</AlertDialogTitle>
          {options.description && <AlertDialogDescription>{options.description}</AlertDialogDescription>}
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{options.cancelLabel ?? "Batal"}</AlertDialogCancel>
          <AlertDialogAction variant={options.destructive ? "destructive" : "default"} onClick={() => settle(true)}>
            {options.confirmLabel ?? "Ya, lanjutkan"}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )

  return { confirm, confirmDialog }
}
