import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { type SppdPageProps } from '@/types/sppd';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

interface Props extends SppdPageProps {
    signaturePath: string | null;
}

const MAX_WIDTH = 600;
const MAX_HEIGHT = 240;
const MAX_FILE_SIZE = 10 * 1024 * 1024;

function saveSignature(dataUrl: string, onSuccess: () => void, onFinish: () => void) {
    router.post(
        route('sppd.signature.update'),
        { signature: dataUrl },
        {
            preserveScroll: true,
            onSuccess,
            onError: (errors) => toast.error(errors.signature ?? 'Tanda tangan gagal disimpan.'),
            onFinish,
        },
    );
}

/** Konversi gambar (PNG/JPG/WebP) menjadi PNG berukuran wajar; opsional membuat latar putih menjadi transparan. */
function imageToPng(file: File, removeWhite: boolean): Promise<string> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);

            const scale = Math.min(1, MAX_WIDTH / image.naturalWidth, MAX_HEIGHT / image.naturalHeight);
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                reject(new Error('Canvas tidak tersedia'));
                return;
            }

            ctx.drawImage(image, 0, 0, canvas.width, canvas.height);

            if (removeWhite) {
                const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const d = pixels.data;
                for (let i = 0; i < d.length; i += 4) {
                    const luminance = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
                    if (luminance >= 235) {
                        d[i + 3] = 0;
                    } else if (luminance > 170) {
                        d[i + 3] = Math.round((d[i + 3] * (235 - luminance)) / 65);
                    }
                }
                ctx.putImageData(pixels, 0, 0);
            }

            resolve(canvas.toDataURL('image/png'));
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('File bukan gambar yang valid'));
        };

        image.src = url;
    });
}

function DrawSignature() {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const [hasDrawn, setHasDrawn] = useState(false);
    const [processing, setProcessing] = useState(false);

    const getPos = (e: React.PointerEvent<HTMLCanvasElement>) => {
        const canvas = e.currentTarget;
        const rect = canvas.getBoundingClientRect();
        return {
            x: ((e.clientX - rect.left) * canvas.width) / rect.width,
            y: ((e.clientY - rect.top) * canvas.height) / rect.height,
        };
    };

    const start = (e: React.PointerEvent<HTMLCanvasElement>) => {
        drawing.current = true;
        e.currentTarget.setPointerCapture(e.pointerId);
        const ctx = e.currentTarget.getContext('2d');
        const { x, y } = getPos(e);
        ctx?.beginPath();
        ctx?.moveTo(x, y);
    };

    const move = (e: React.PointerEvent<HTMLCanvasElement>) => {
        if (!drawing.current) return;
        const ctx = e.currentTarget.getContext('2d');
        const { x, y } = getPos(e);
        if (ctx) {
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111';
            ctx.lineTo(x, y);
            ctx.stroke();
        }
        setHasDrawn(true);
    };

    const end = () => {
        drawing.current = false;
    };

    const clear = () => {
        const canvas = canvasRef.current;
        canvas?.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height);
        setHasDrawn(false);
    };

    const save = () => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        setProcessing(true);
        saveSignature(canvas.toDataURL('image/png'), clear, () => setProcessing(false));
    };

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
                Gambar tanda tangan Anda pada kotak di bawah ini menggunakan mouse atau layar sentuh.
            </p>

            <canvas
                ref={canvasRef}
                width={500}
                height={200}
                className="w-full touch-none rounded-lg border border-input bg-white"
                onPointerDown={start}
                onPointerMove={move}
                onPointerUp={end}
                onPointerCancel={end}
            />

            <div className="flex gap-2">
                <Button variant="outline" type="button" onClick={clear} disabled={!hasDrawn}>
                    Hapus
                </Button>
                <Button type="button" disabled={!hasDrawn || processing} onClick={save}>
                    {processing ? 'Menyimpan...' : 'Simpan Tanda Tangan'}
                </Button>
            </div>
        </div>
    );
}

function UploadSignature() {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [removeWhite, setRemoveWhite] = useState(true);
    const [preview, setPreview] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }

        let cancelled = false;
        imageToPng(file, removeWhite)
            .then((dataUrl) => !cancelled && setPreview(dataUrl))
            .catch(() => {
                if (cancelled) return;
                setPreview(null);
                toast.error('File tidak dapat dibaca sebagai gambar.');
            });

        return () => {
            cancelled = true;
        };
    }, [file, removeWhite]);

    const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0] ?? null;

        if (selected && !selected.type.startsWith('image/')) {
            toast.error('File harus berupa gambar (PNG atau JPG).');
            e.target.value = '';
            return;
        }

        if (selected && selected.size > MAX_FILE_SIZE) {
            toast.error('Ukuran gambar maksimal 10 MB.');
            e.target.value = '';
            return;
        }

        setFile(selected);
    };

    const reset = () => {
        setFile(null);
        if (inputRef.current) inputRef.current.value = '';
    };

    const save = () => {
        if (!preview) return;

        setProcessing(true);
        saveSignature(preview, reset, () => setProcessing(false));
    };

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
                Unggah foto atau hasil scan tanda tangan Anda (PNG atau JPG). Gambar akan dikonversi otomatis ke PNG.
            </p>

            <Field>
                <FieldLabel htmlFor="signature-file">File gambar</FieldLabel>
                <Input ref={inputRef} id="signature-file" type="file" accept="image/png,image/jpeg" onChange={onFileChange} />
            </Field>

            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <Checkbox checked={removeWhite} onCheckedChange={(checked) => setRemoveWhite(checked)} />
                Hapus latar putih otomatis
            </label>

            {preview && (
                <div>
                    <p className="mb-2 text-sm font-medium">Pratinjau:</p>
                    <img src={preview} alt="Pratinjau tanda tangan" className="h-32 rounded border bg-white p-2" />
                </div>
            )}

            <div className="flex gap-2">
                <Button variant="outline" type="button" onClick={reset} disabled={!file}>
                    Batal
                </Button>
                <Button type="button" disabled={!preview || processing} onClick={save}>
                    {processing ? 'Menyimpan...' : 'Simpan Tanda Tangan'}
                </Button>
            </div>
        </div>
    );
}

export default function Edit() {
    const { signaturePath } = usePage<Props>().props;

    return (
        <>
            <Head title="Tanda Tangan Saya" />

            <div className="flex max-w-xl flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold">Tanda Tangan Saya</h1>
                    <p className="text-sm text-muted-foreground">Tanda tangan digital yang ditempelkan pada SPPD</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Gambar Tanda Tangan</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <Tabs defaultValue="tulis">
                            <TabsList>
                                <TabsTrigger value="tulis">Tulis Tangan</TabsTrigger>
                                <TabsTrigger value="unggah">Unggah Gambar</TabsTrigger>
                            </TabsList>
                            <TabsContent value="tulis" className="pt-2">
                                <DrawSignature />
                            </TabsContent>
                            <TabsContent value="unggah" className="pt-2">
                                <UploadSignature />
                            </TabsContent>
                        </Tabs>

                        <p className="text-sm text-muted-foreground">
                            Tanda tangan ini akan otomatis ditempelkan pada SPPD saat Anda menyetujui pengajuan.
                        </p>

                        {signaturePath && (
                            <div className="border-t pt-4">
                                <p className="mb-2 text-sm font-medium">Tanda tangan tersimpan saat ini:</p>
                                <img
                                    key={signaturePath}
                                    src={`/uploads/${signaturePath}`}
                                    alt="Tanda tangan"
                                    className="h-24 rounded border bg-white p-2"
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
