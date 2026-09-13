import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import stockTakes from '@/routes/stock-takes';
import { formatDateTime } from '@/lib/format';

type TakeRow = {
    id: number;
    take_no: string;
    status: string;
    created_at: string;
    counted_at: string | null;
    branch: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
};

type Props = {
    takes: {
        data: TakeRow[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { status?: string };
};

const statusVariant: Record<string, 'outline' | 'secondary' | 'destructive' | 'default'> = {
    counted: 'outline',
    pending: 'secondary',
    approved: 'default',
    cancelled: 'destructive',
};

export default function StockTakesIndex({ takes, filters }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(stockTakes.index().url, params, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Stock Takes" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Stock Takes"
                        description="Physically count stock and reconcile variances."
                    />
                    <Button asChild>
                        <Link href={stockTakes.create().url}>Start stock take</Link>
                    </Button>
                </div>

                <div className="flex items-end gap-3">
                    <Label text="Status" />
                    <select
                        className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                        defaultValue={filters.status ?? ''}
                        onChange={(e) => applyWith({ status: e.target.value === 'all' ? '' : e.target.value })}
                    >
                        <option value="all">All statuses</option>
                        <option value="counted">Counted</option>
                        <option value="pending">Pending approval</option>
                        <option value="approved">Approved</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Take No.</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Counted By</TableHead>
                                <TableHead>Counted At</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {takes.data.map((t) => (
                                <TableRow key={t.id}>
                                    <TableCell className="font-mono text-sm font-medium">{t.take_no}</TableCell>
                                    <TableCell>{t.branch?.name ?? '—'}</TableCell>
                                    <TableCell>{t.creator?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {t.counted_at ? formatDateTime(t.counted_at) : '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant[t.status] ?? 'outline'}>{t.status}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={stockTakes.show.url({ take: t.id })}>Open</Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {takes.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                        No stock takes found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {takes.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {takes.current_page} of {takes.last_page} · {takes.total} total
                        </span>
                        <div className="flex gap-2">
                            {takes.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={takes.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {takes.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={takes.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function Label({ text }: { text: string }) {
    return <span className="text-muted-foreground text-sm">{text}</span>;
}

StockTakesIndex.layout = {
    breadcrumbs: [{ title: 'Stock Takes', href: stockTakes.index().url }],
};