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
import stockTransfers from '@/routes/stock-transfers';
import { formatDate } from '@/lib/format';

type TransferRow = {
    id: number;
    transfer_no: string;
    status: string;
    shipped_at: string | null;
    received_at: string | null;
    created_at: string;
    from_branch: { id: number; name: string };
    to_branch: { id: number; name: string };
    items: { id: number }[];
    creator: { id: number; name: string } | null;
};

type Props = {
    transfers: {
        data: TransferRow[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    branches: { id: number; name: string }[];
    filters: { status?: string };
};

const statusVariant: Record<string, 'outline' | 'secondary' | 'destructive' | 'default'> = {
    draft: 'outline',
    in_transit: 'secondary',
    received: 'default',
    cancelled: 'destructive',
};

export default function StockTransfersIndex({ transfers, branches, filters }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(stockTransfers.index().url, params, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Stock Transfers" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Stock Transfers"
                        description="Move stock between branches."
                    />
                    <Button asChild>
                        <Link href={stockTransfers.create().url}>New transfer</Link>
                    </Button>
                </div>

                <div className="flex items-end gap-3">
                    <div className="grid gap-2">
                        <Label text="Status" />
                        <select
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.status ?? ''}
                            onChange={(e) => applyWith({ status: e.target.value === 'all' ? '' : e.target.value })}
                        >
                            <option value="all">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="in_transit">In transit</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Transfer No.</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Lines</TableHead>
                                <TableHead>Shipped</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transfers.data.map((t) => (
                                <TableRow key={t.id}>
                                    <TableCell className="font-mono text-sm font-medium">{t.transfer_no}</TableCell>
                                    <TableCell>{t.from_branch?.name}</TableCell>
                                    <TableCell>{t.to_branch?.name}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{t.items.length} lines</Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{t.shipped_at ? formatDate(t.shipped_at) : '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant[t.status] ?? 'outline'}>{t.status}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={stockTransfers.show.url({ transfer: t.id })}>View</Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {transfers.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                        No transfers found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {transfers.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {transfers.current_page} of {transfers.last_page} · {transfers.total} total
                        </span>
                        <div className="flex gap-2">
                            {transfers.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={transfers.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {transfers.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={transfers.next_page_url}>Next</Link>
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

StockTransfersIndex.layout = {
    breadcrumbs: [{ title: 'Stock Transfers', href: stockTransfers.index().url }],
};