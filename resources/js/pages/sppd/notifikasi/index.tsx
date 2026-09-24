import { DataPagination } from '@/components/sppd/data-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { type AppNotification, type SppdPageProps, type PaginatedData } from '@/types/sppd';
import { Head, router, usePage } from '@inertiajs/react';
import { BellIcon, CheckCheckIcon } from 'lucide-react';

type Filter = 'all' | 'unread';

interface Props extends SppdPageProps {
    items: PaginatedData<AppNotification>;
    counts: { all: number; unread: number };
    filter: Filter;
}

const FILTERS: { value: Filter; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'unread', label: 'Belum dibaca' },
];

function formatWaktu(iso: string) {
    return new Date(iso).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Index() {
    const { items, counts, filter } = usePage<Props>().props;

    function changeFilter(value: string) {
        router.get(
            route('sppd.notifications.index'),
            value === 'unread' ? { filter: 'unread' } : {},
            { preserveState: true },
        );
    }

    function open(notification: AppNotification) {
        const target = route('sppd.pengajuan.show', notification.data.pengajuan_id);

        if (notification.read_at) {
            router.visit(target);
            return;
        }

        router.patch(route('sppd.notifications.read', notification.id), {}, {
            preserveScroll: true,
            onFinish: () => router.visit(target),
        });
    }

    function markAllRead() {
        router.patch(route('sppd.notifications.read-all'), {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Notifikasi" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Notifikasi</h1>
                        <p className="text-sm text-muted-foreground">
                            Riwayat perubahan status pengajuan SPPD yang berkaitan dengan Anda
                        </p>
                    </div>
                    <Button variant="outline" disabled={counts.unread === 0} onClick={markAllRead}>
                        <CheckCheckIcon /> Tandai semua dibaca
                    </Button>
                </div>

                <Tabs value={filter} onValueChange={changeFilter}>
                    <TabsList>
                        {FILTERS.map((f) => (
                            <TabsTrigger key={f.value} value={f.value}>
                                {f.label}
                                <Badge variant="secondary" className="ml-1">
                                    {counts[f.value]}
                                </Badge>
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>

                <Card>
                    <CardContent className="divide-y p-0">
                        {items.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 p-10 text-center text-muted-foreground">
                                <BellIcon className="size-8" />
                                <p className="text-sm">Belum ada notifikasi</p>
                            </div>
                        ) : (
                            items.data.map((notification) => (
                                <button
                                    key={notification.id}
                                    type="button"
                                    onClick={() => open(notification)}
                                    className={cn(
                                        'flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted',
                                        !notification.read_at && 'bg-accent/40',
                                    )}
                                >
                                    <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <BellIcon className="size-4" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium">
                                                Pengajuan SPPD #{notification.data.pengajuan_id}
                                            </span>
                                            {!notification.read_at && (
                                                <span className="size-1.5 rounded-full bg-primary" aria-hidden />
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">{notification.data.pesan}</p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {formatWaktu(notification.created_at)}
                                        </p>
                                    </div>
                                </button>
                            ))
                        )}
                    </CardContent>
                </Card>

                {items.data.length > 0 && <DataPagination paginator={items} />}
            </div>
        </>
    );
}
