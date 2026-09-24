import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { type SppdPageProps } from '@/types/sppd';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckIcon, ExternalLinkIcon } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Template {
    value: 'landscape' | 'portrait' | 'landscape_new';
    label: string;
    deskripsi: string;
}

interface Props extends SppdPageProps {
    saatIni: Template['value'];
    templates: Template[];
}

const Garis = ({ className }: { className?: string }) => <div className={cn('h-[3px] rounded-full bg-muted-foreground/30', className)} />;

/** Sketsa tata letak agar perbedaan tiap template terlihat sekilas. */
function Sketsa({ jenis }: { jenis: Template['value'] }) {
    if (jenis === 'landscape_new') {
        return (
            <div className="flex aspect-[297/210] w-full max-w-64 gap-2 rounded border bg-white p-2 dark:bg-neutral-200">
                <div className="flex flex-1 flex-col gap-1">
                    <div className="flex items-center gap-1">
                        <div className="size-3 rounded-full bg-neutral-400/60" />
                        <div className="flex flex-1 flex-col items-center gap-0.5">
                            <Garis className="w-2/3" />
                            <Garis className="w-1/2" />
                        </div>
                    </div>
                    <div className="h-[2px] bg-neutral-500/50" />
                    <div className="flex flex-1 flex-col divide-y divide-neutral-400/40 rounded-sm border border-neutral-400/50">
                        {Array.from({ length: 7 }).map((_, i) => (
                            <div key={i} className="flex flex-1 items-center gap-1 px-1">
                                <div className="h-[3px] w-1/3 rounded-full bg-muted-foreground/30" />
                                <div className="h-[3px] w-1/3 rounded-full bg-muted-foreground/20" />
                            </div>
                        ))}
                    </div>
                    <div className="mt-0.5 flex items-center justify-between">
                        <div className="size-4 rounded-sm border border-neutral-400/60" />
                        <div className="w-1/3">
                            <Garis />
                        </div>
                    </div>
                </div>
                <div className="flex flex-1 flex-col gap-1 border-l border-dashed pl-2">
                    <Garis className="w-2/3" />
                    <div className="h-8 rounded-sm border border-neutral-400/50" />
                    <div className="flex flex-1 gap-1">
                        <div className="flex-1 rounded-sm border border-neutral-400/50" />
                        <div className="flex-1 rounded-sm border border-neutral-400/50" />
                    </div>
                    <div className="h-9 rounded-sm border border-neutral-400/50" />
                </div>
            </div>
        );
    }

    if (jenis === 'landscape') {
        return (
            <div className="flex aspect-[297/210] w-full max-w-64 gap-2 rounded border bg-white p-2 dark:bg-neutral-200">
                <div className="flex flex-1 flex-col gap-1">
                    <div className="h-4 rounded-sm bg-neutral-400/60" />
                    {Array.from({ length: 9 }).map((_, i) => (
                        <Garis key={i} className={i % 3 === 2 ? 'w-2/3' : ''} />
                    ))}
                    <div className="mt-auto ml-auto w-1/2">
                        <Garis />
                    </div>
                </div>
                <div className="flex flex-1 flex-col gap-1 border-l pl-2">
                    <Garis />
                    <Garis className="w-3/4" />
                    <div className="h-6 rounded-sm border border-dashed border-neutral-400/60" />
                    <div className="h-6 rounded-sm border border-dashed border-neutral-400/60" />
                    <Garis className="w-2/3" />
                </div>
            </div>
        );
    }

    return (
        <div className="flex gap-3">
            {[0, 1].map((halaman) => (
                <div key={halaman} className="flex aspect-[210/297] w-24 flex-col gap-1 rounded border bg-white p-2 dark:bg-neutral-200">
                    {halaman === 0 ? (
                        <>
                            <div className="h-3 rounded-sm bg-neutral-400/60" />
                            {Array.from({ length: 8 }).map((_, i) => (
                                <Garis key={i} className={i % 3 === 2 ? 'w-2/3' : ''} />
                            ))}
                            <div className="mt-auto ml-auto w-1/2">
                                <Garis />
                            </div>
                        </>
                    ) : (
                        <>
                            <Garis className="w-2/3" />
                            <div className="h-8 rounded-sm border border-dashed border-neutral-400/60" />
                            <div className="flex flex-1 gap-1">
                                <div className="flex-1 rounded-sm border border-dashed border-neutral-400/60" />
                                <div className="flex-1 rounded-sm border border-dashed border-neutral-400/60" />
                            </div>
                            <div className="h-8 rounded-sm border border-dashed border-neutral-400/60" />
                        </>
                    )}
                </div>
            ))}
        </div>
    );
}

export default function TemplateSppd() {
    const { saatIni, templates } = usePage<Props>().props;
    const { data, setData, put, processing } = useForm({ template: saatIni });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('sppd.template-sppd.update'), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Template SPPD" />

            <div className="flex max-w-6xl flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold">Template SPPD</h1>
                    <p className="text-sm text-muted-foreground">
                        Pilih tampilan PDF SPPD yang dipakai saat dokumen diunduh. Isi dokumen sama pada semua template.
                    </p>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Pilih Template</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5">
                            <div role="radiogroup" aria-label="Template SPPD" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {templates.map((template) => {
                                    const dipilih = data.template === template.value;

                                    return (
                                        <div
                                            key={template.value}
                                            className={cn(
                                                'flex flex-col gap-3 rounded-lg border p-4 transition-colors',
                                                dipilih ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'hover:bg-muted/40',
                                            )}
                                        >
                                            <button
                                                type="button"
                                                role="radio"
                                                aria-checked={dipilih}
                                                onClick={() => setData('template', template.value)}
                                                className="flex flex-col gap-3 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <div className="flex min-h-40 items-center justify-center overflow-hidden rounded-md bg-muted/50 p-3">
                                                    <Sketsa jenis={template.value} />
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium">{template.label}</span>
                                                    {saatIni === template.value && <Badge variant="success">Dipakai saat ini</Badge>}
                                                    {dipilih && saatIni !== template.value && (
                                                        <Badge variant="secondary">
                                                            <CheckIcon /> Dipilih
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">{template.deskripsi}</p>
                                            </button>
                                            <a
                                                href={route('sppd.template-sppd.pratinjau', template.value)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex w-fit items-center gap-1 text-sm text-primary hover:underline"
                                            >
                                                Lihat pratinjau PDF <ExternalLinkIcon className="size-3.5" />
                                            </a>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing || data.template === saatIni}>
                                    {processing ? 'Menyimpan...' : 'Simpan Template'}
                                </Button>
                                {data.template === saatIni && <span className="text-sm text-muted-foreground">Template ini sedang dipakai.</span>}
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
