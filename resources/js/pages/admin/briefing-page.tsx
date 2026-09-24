import * as React from "react"
import { Html5Qrcode, Html5QrcodeScannerState } from "html5-qrcode"
import { toast } from "sonner"
import { Clock, Download, Pencil } from "lucide-react"

import { api, ApiError, downloadFile } from "@/lib/api"
import type { BriefingCheckinResult, BriefingRekapRow, Guru, StatusKehadiran } from "@/lib/types"
import {
  STATUS_OPTIONS,
  formatJam,
  statusBadgeVariant,
  statusLabel,
  toTimeInputValue,
} from "@/lib/attendance-format"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { DataPagination } from "@/components/data-pagination"
import { DatePicker } from "@/components/date-picker"
import { GuruCombobox } from "@/components/guru-combobox"
import { TimePicker } from "@/components/time-picker"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

const READER_ID = "briefing-qr-reader"
const SAME_TOKEN_COOLDOWN_MS = 4000

type CameraStatus = "starting" | "scanning" | "error"

interface Props {
  guruList: Guru[]
  rows: BriefingRekapRow[]
  from: string
  to: string
  /** null = tidak ada jadwal briefing hari ini (hari libur atau memang tidak
   * dicentang di Pengaturan). */
  jamBriefing: string | null
  /** null = tidak ada gerbang penutup - absen briefing tetap terbuka
   * sampai akhir hari begitu jamBriefing kebuka. */
  jamSelesaiBriefing: string | null
}

/** Whether `hhmm` ("HH:mm") has been reached yet today, as of `now`. */
function isTimeReached(hhmm: string, now: Date): boolean {
  const [hour, minute] = hhmm.split(":").map(Number)
  const gate = new Date(now)
  gate.setHours(hour, minute, 0, 0)
  return now >= gate
}

export function BriefingPage({
  guruList,
  rows: initialRows,
  from: initialFrom,
  to: initialTo,
  jamBriefing,
  jamSelesaiBriefing,
}: Props) {
  const [cameraStatus, setCameraStatus] = React.useState<CameraStatus>("starting")
  const [cameraError, setCameraError] = React.useState<string | null>(null)
  const scannerRef = React.useRef<Html5Qrcode | null>(null)
  const busyRef = React.useRef(false)
  const lastTokenRef = React.useRef<{ token: string; at: number } | null>(null)

  const [now, setNow] = React.useState(() => new Date())
  React.useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 15_000)
    return () => clearInterval(timer)
  }, [])
  const isBriefingOpen = jamBriefing !== null && isTimeReached(jamBriefing, now)
  const isBriefingClosed = jamSelesaiBriefing !== null && isTimeReached(jamSelesaiBriefing, now)
  const isBriefingActive = isBriefingOpen && !isBriefingClosed

  const [guruId, setGuruId] = React.useState<string>("all")
  const [from, setFrom] = React.useState(initialFrom)
  const [to, setTo] = React.useState(initialTo)
  const [rows, setRows] = React.useState<BriefingRekapRow[]>(initialRows)
  const [loading, setLoading] = React.useState(false)
  const [exporting, setExporting] = React.useState(false)
  const [page, setPage] = React.useState(1)
  const [pageSize, setPageSize] = React.useState(50)

  const [editing, setEditing] = React.useState<BriefingRekapRow | null>(null)
  const [editForm, setEditForm] = React.useState({
    status: "HADIR" as StatusKehadiran,
    waktu: "",
    catatan: "",
  })
  const [editSaving, setEditSaving] = React.useState(false)
  const [editError, setEditError] = React.useState<string | null>(null)

  const loadRows = React.useCallback(() => {
    setLoading(true)
    api
      .get<BriefingRekapRow[]>("/briefing/rekap", {
        from,
        to,
        guruId: guruId === "all" ? undefined : guruId,
      })
      .then(setRows)
      .finally(() => setLoading(false))
  }, [from, to, guruId])

  const isFirstLoad = React.useRef(true)
  React.useEffect(() => {
    if (isFirstLoad.current) {
      isFirstLoad.current = false
      return
    }
    loadRows()
    setPage(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loadRows])

  async function handleScan(qrToken: string) {
    const token = qrToken.trim()
    if (!token) return

    const nowMs = Date.now()
    if (lastTokenRef.current?.token === token && nowMs - lastTokenRef.current.at < SAME_TOKEN_COOLDOWN_MS) {
      return
    }
    if (busyRef.current) return
    busyRef.current = true
    lastTokenRef.current = { token, at: nowMs }

    try {
      const result = await api.post<BriefingCheckinResult>("/briefing/scan", { qrToken: token })
      toast.success(`${result.nama} berhasil absen briefing`)
      loadRows()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal memproses scan")
    } finally {
      busyRef.current = false
    }
  }

  React.useEffect(() => {
    if (!isBriefingActive) return

    let cancelled = false
    const scanner = new Html5Qrcode(READER_ID)
    scannerRef.current = scanner

    function stopIfRunning() {
      const state = scanner.getState()
      if (state === Html5QrcodeScannerState.SCANNING || state === Html5QrcodeScannerState.PAUSED) {
        return scanner.stop().catch(() => {}).finally(() => scanner.clear())
      }
      return Promise.resolve()
    }

    async function start() {
      setCameraStatus("starting")
      setCameraError(null)
      try {
        await scanner.start(
          { facingMode: "environment" },
          { fps: 10, qrbox: { width: 260, height: 260 } },
          (decodedText) => {
            void handleScan(decodedText)
          },
          () => {
            // per-frame decode failure, ignore
          }
        )
        if (cancelled) {
          void stopIfRunning()
          return
        }
        setCameraStatus("scanning")
      } catch {
        if (!cancelled) {
          setCameraStatus("error")
          setCameraError(
            "Tidak bisa mengakses kamera. Pastikan Anda mengizinkan akses kamera di browser."
          )
        }
      }
    }

    start()

    return () => {
      cancelled = true
      scannerRef.current = null
      void stopIfRunning()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isBriefingActive])

  async function handleExport() {
    setExporting(true)
    try {
      await downloadFile(
        "/briefing/rekap/export",
        { from, to, guruId: guruId === "all" ? undefined : guruId },
        `rekap-briefing-${from}-${to}.xlsx`
      )
      toast.success("Excel berhasil diunduh")
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal mengunduh Excel")
    } finally {
      setExporting(false)
    }
  }

  function openEdit(row: BriefingRekapRow) {
    setEditing(row)
    setEditError(null)
    setEditForm({
      status: row.status ?? "HADIR",
      waktu: toTimeInputValue(row.waktu),
      catatan: "",
    })
  }

  async function handleSaveEdit(event: React.FormEvent) {
    event.preventDefault()
    if (!editing) return

    setEditSaving(true)
    setEditError(null)
    try {
      await api.post("/briefing/manual", {
        guruId: editing.guruId,
        tanggal: editing.tanggal,
        status: editForm.status,
        waktu: editForm.waktu,
        catatan: editForm.catatan,
      })
      toast.success("Koreksi absen briefing berhasil disimpan")
      setEditing(null)
      loadRows()
    } catch (err) {
      setEditError(err instanceof ApiError ? err.message : "Gagal menyimpan koreksi")
    } finally {
      setEditSaving(false)
    }
  }

  const paginatedRows = rows.slice((page - 1) * pageSize, page * pageSize)
  const hadirCount = rows.filter((r) => r.status === "HADIR").length
  const alpaCount = rows.filter((r) => r.status === "ALPA").length

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold">Absen Briefing</h1>
        <p className="text-sm text-muted-foreground">
          Scan QR guru/kepsek satu per satu untuk absen briefing - otomatis kebuka mulai jam
          briefing yang diatur di Pengaturan
        </p>
      </div>

      {!isBriefingActive ? (
        <Card className="mx-auto w-full max-w-sm">
          <CardContent className="flex flex-col items-center gap-2 py-10 text-center text-muted-foreground">
            <Clock className="size-8" />
            <p className="text-sm">
              {jamBriefing === null
                ? "Tidak ada jadwal briefing hari ini"
                : !isBriefingOpen
                  ? `Belum jam briefing (mulai ${jamBriefing})`
                  : `Absen briefing sudah ditutup (berakhir jam ${jamSelesaiBriefing})`}
            </p>
          </CardContent>
        </Card>
      ) : (
        <>
          <Card className="mx-auto w-full max-w-sm overflow-hidden py-0">
            <CardContent className="p-0">
              <div id={READER_ID} className="aspect-square w-full bg-black" />
            </CardContent>
          </Card>

          <div className="text-center text-sm">
            {cameraStatus === "starting" && (
              <p className="text-muted-foreground">Memulai kamera...</p>
            )}
            {cameraStatus === "error" && <p className="text-destructive">{cameraError}</p>}
            {cameraStatus === "scanning" && (
              <p className="text-muted-foreground">
                Arahkan kamera ke QR guru/kepsek satu per satu - kamera tetap menyala, tinggal
                lanjut ke orang berikutnya
              </p>
            )}
          </div>
        </>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          Hadir: {hadirCount} · Alpa: {alpaCount}
        </p>
        <Button variant="outline" onClick={handleExport} disabled={exporting}>
          <Download /> {exporting ? "Mengunduh..." : "Export Excel"}
        </Button>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <Field className="w-auto">
          <FieldLabel htmlFor="from">Dari</FieldLabel>
          <DatePicker id="from" value={from} onChange={setFrom} />
        </Field>
        <Field className="w-auto">
          <FieldLabel htmlFor="to">Sampai</FieldLabel>
          <DatePicker id="to" value={to} onChange={setTo} />
        </Field>
        <Field className="w-auto">
          <FieldLabel htmlFor="guru">Guru</FieldLabel>
          <GuruCombobox id="guru" guruList={guruList} value={guruId} onChange={setGuruId} className="w-48" />
        </Field>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Tanggal</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Waktu</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Memuat...
                </TableCell>
              </TableRow>
            ) : paginatedRows.filter((r) => r.status !== null).length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Tidak ada jadwal briefing pada rentang ini
                </TableCell>
              </TableRow>
            ) : (
              paginatedRows
                .filter((r) => r.status !== null)
                .map((row, index) => (
                  <TableRow key={`${row.guruId}-${row.tanggal}`}>
                    <TableCell className="text-muted-foreground">
                      {(page - 1) * pageSize + index + 1}
                    </TableCell>
                    <TableCell>{row.tanggal}</TableCell>
                    <TableCell>{row.nama}</TableCell>
                    <TableCell>{formatJam(row.waktu)}</TableCell>
                    <TableCell>
                      {row.status && (
                        <Badge variant={statusBadgeVariant(row.status)}>{statusLabel(row.status)}</Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon-sm" onClick={() => openEdit(row)}>
                        <Pencil />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
            )}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        page={page}
        pageSize={pageSize}
        total={rows.length}
        onPageChange={setPage}
        onPageSizeChange={(size) => {
          setPageSize(size)
          setPage(1)
        }}
      />

      <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent>
          {editing && (
            <>
              <DialogHeader>
                <DialogTitle>Koreksi Absen Briefing</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleSaveEdit}>
                <FieldGroup>
                  <div>
                    <div className="font-medium">{editing.nama}</div>
                    <div className="text-xs text-muted-foreground">{editing.tanggal}</div>
                  </div>
                  <Field>
                    <FieldLabel htmlFor="editWaktu">Jam Masuk</FieldLabel>
                    <div className="flex items-center gap-2">
                      <TimePicker
                        id="editWaktu"
                        value={editForm.waktu}
                        onChange={(v) => setEditForm((f) => ({ ...f, waktu: v }))}
                      />
                      {editForm.waktu && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => setEditForm((f) => ({ ...f, waktu: "" }))}
                        >
                          Kosongkan
                        </Button>
                      )}
                    </div>
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="editStatus">Status</FieldLabel>
                    <Select
                      items={Object.fromEntries(STATUS_OPTIONS.map((s) => [s, statusLabel(s)]))}
                      value={editForm.status}
                      onValueChange={(v) => v && setEditForm((f) => ({ ...f, status: v as StatusKehadiran }))}
                    >
                      <SelectTrigger id="editStatus" className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {STATUS_OPTIONS.map((s) => (
                          <SelectItem key={s} value={s}>
                            {statusLabel(s)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="editCatatan">Alasan (opsional)</FieldLabel>
                    <Textarea
                      id="editCatatan"
                      value={editForm.catatan}
                      onChange={(e) => setEditForm((f) => ({ ...f, catatan: e.target.value }))}
                      placeholder="Alasan koreksi, misal: lupa scan, konfirmasi hadir briefing"
                    />
                  </Field>
                  {editError && (
                    <p className="text-sm text-destructive" role="alert">
                      {editError}
                    </p>
                  )}
                  <DialogFooter>
                    <Button type="submit" disabled={editSaving}>
                      {editSaving ? "Menyimpan..." : "Simpan Koreksi"}
                    </Button>
                  </DialogFooter>
                </FieldGroup>
              </form>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
