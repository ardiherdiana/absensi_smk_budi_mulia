import * as React from "react"
import { Link } from "@inertiajs/react"
import { ArrowLeft, CheckCircle2, ScanLine, XCircle } from "lucide-react"

import { api, ApiError } from "@/lib/api"
import type { CheckinResult, Settings } from "@/lib/types"

type Status = "idle" | "processing" | "success" | "error"

// How long a success/error result stays on screen before the station resets
// back to "waiting for scan" - long enough for the guru to read it, short
// enough that the next person isn't stuck looking at someone else's result.
const RESULT_RESET_MS = 4000

/** Scan station for the school's USB barcode/QR scanner (a desktop device
 * that behaves like a keyboard - it "types" the decoded QR content followed
 * by Enter into whatever has focus). Opened on a PC/tablet at school,
 * logged in as ADMIN; a guru presents their own static QR (phone or printed
 * card) to the physical device instead of scanning anything themselves. */
export function KioskPage({ settings }: { settings: Settings }) {
  const [now, setNow] = React.useState(new Date())
  const [inputValue, setInputValue] = React.useState("")
  const [status, setStatus] = React.useState<Status>("idle")
  const [result, setResult] = React.useState<CheckinResult | null>(null)
  const [message, setMessage] = React.useState<string | null>(null)
  const inputRef = React.useRef<HTMLInputElement>(null)
  const busyRef = React.useRef(false)
  const resetTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null)

  React.useEffect(() => {
    const clockTimer = setInterval(() => setNow(new Date()), 1000)
    return () => clearInterval(clockTimer)
  }, [])

  // The scanner device just sends keystrokes to whatever element has focus,
  // so this input must always stay focused - reclaim it on any stray click
  // and right after every scan result clears.
  const focusInput = React.useCallback(() => {
    inputRef.current?.focus()
  }, [])

  React.useEffect(() => {
    focusInput()
    document.addEventListener("click", focusInput)
    return () => document.removeEventListener("click", focusInput)
  }, [focusInput])

  React.useEffect(() => {
    return () => {
      if (resetTimerRef.current) clearTimeout(resetTimerRef.current)
    }
  }, [])

  async function handleScan(qrToken: string) {
    if (busyRef.current || !qrToken.trim()) return
    busyRef.current = true
    if (resetTimerRef.current) clearTimeout(resetTimerRef.current)
    setStatus("processing")
    try {
      const res = await api.post<CheckinResult>("/kiosk/scan-qr", { qrToken: qrToken.trim() })
      setResult(res)
      setMessage(null)
      setStatus("success")
    } catch (err) {
      setResult(null)
      setMessage(err instanceof ApiError ? err.message : "Gagal memproses scan")
      setStatus("error")
    } finally {
      busyRef.current = false
      resetTimerRef.current = setTimeout(() => {
        setStatus("idle")
        setResult(null)
        setMessage(null)
      }, RESULT_RESET_MS)
    }
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Enter") {
      event.preventDefault()
      const value = inputValue
      setInputValue("")
      handleScan(value)
    }
  }

  return (
    <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-neutral-950 p-6 text-white">
      <div className="text-center">
        <p className="text-sm tracking-wide text-white/60 uppercase">
          {settings?.namaSekolah ?? "Sekolah"}
        </p>
        <h1 className="text-2xl font-semibold">Absensi Guru</h1>
        <p className="mt-1 font-mono text-4xl tabular-nums">
          {now.toLocaleTimeString("id-ID", { hour12: false })}
        </p>
        <p className="text-sm text-white/60">
          {now.toLocaleDateString("id-ID", {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
          })}
        </p>
      </div>

      <div className="flex w-full max-w-sm flex-col items-center gap-4 rounded-2xl bg-white/5 p-8 text-center ring-1 ring-white/10">
        {status === "idle" && (
          <>
            <ScanLine className="size-16 text-white/40" />
            <p className="text-white/70">Arahkan / tunjukkan QR guru ke alat scan</p>
          </>
        )}
        {status === "processing" && (
          <>
            <ScanLine className="size-16 animate-pulse text-white/60" />
            <p className="text-white/70">Memproses...</p>
          </>
        )}
        {status === "success" && result && (
          <>
            <CheckCircle2 className="size-16 text-emerald-400" />
            <p className="text-lg font-medium">{result.nama}</p>
            <p className="text-white/70">
              {result.type === "MASUK" ? "Absen masuk berhasil" : "Absen pulang berhasil"}
              {result.statusMasuk ? ` (${result.statusMasuk === "TELAT" ? "Telat" : "Hadir"})` : ""}
            </p>
            <p className="font-mono text-sm text-white/50">
              {new Date(result.jam).toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
              })}
            </p>
          </>
        )}
        {status === "error" && (
          <>
            <XCircle className="size-16 text-red-400" />
            <p className="text-white/80">{message}</p>
          </>
        )}

        <input
          ref={inputRef}
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
          onBlur={focusInput}
          autoFocus
          placeholder="Menunggu scan..."
          className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-1.5 text-center text-sm text-white/60 outline-none placeholder:text-white/30"
        />
      </div>

      <p className="max-w-sm text-center text-xs text-white/40">
        Alat scan mengetik otomatis ke kotak di atas - bisa juga diketik manual buat tes.
      </p>

      <Link
        href="/dashboard"
        className="absolute top-4 left-4 flex items-center gap-1.5 text-sm text-white/70 hover:text-white"
      >
        <ArrowLeft className="size-4" />
        Kembali ke dashboard
      </Link>
    </div>
  )
}
