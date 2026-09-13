import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import stockRoutes from '@/routes/stock';
import stockTransfersRoutes from '@/routes/stock-transfers';
import { formatDateTime } from '@/lib/format';

type Row = {
    id: number;
    quantity: string;
    status: string;
    notes: string | null;
    created_at: string;
    reviewed_at: string | null;
    product: { id: number; name: string; sku: string } | null;
    product_variant: { id: number; name: string } | null;
    branch: { id: number; name: string; type: string } | null;
    source_branch: { id: number; name: string; type: string } | null;
    requester: { id: number; name: string } | null;
    reviewer: { id: number; name: string } | null;
    transfer: {
        id: number;
        transfer_no: string;
        status: string;
    } | null;
};

type Props = {
    requests: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    status: 'pending' | 'dispatched' | 'received' | 'rejected';
    counts: { pending: number; dispatched: number; received: number; rejected: number };
};

const statusVariant: Record<string, 'outline' | 'secondary' | 'destructive' | 'default'> = {
    pending: 'outline',
    dispatched: 'secondary',
    received: 'default',
    rejected: 'destructive',
};

export default function StockRequests({ requests, status, counts }: Props) {
    const tabs: { key: 'pending' | 'dispatched' | 'received' | 'rejected'; label: string; count: number }[] = [
        { key: 'pending', label: 'Pending', count: counts.pending },
        { key: 'dispatched', label: 'Dispatched', count: counts.dispatched },
        { key: 'received', label: 'Received', count: counts.received },
        { key: 'rejected', label: 'Rejected', count: counts.rejected },
    ];
    const [reviewConfirm, setReviewConfirm] = useState<{ action: 'approve' | 'reject'; row: Row } | null>(null);

    const setStatus = (next: 'pending' | 'dispatched' | 'received' | 'rejected') => {
        if (next === status) return;
        router.get(stockRoutes.requests().url, { status: next }, { preserveState: true, preserveScroll: true });
    };

    const review = (action: 'approve' | 'reject', row: Row) => {
        setReviewConfirm({ action, row });
    };

    const confirmReview = () => {
        if (!reviewConfirm) return;
        const { action, row } = reviewConfirm;
        setReviewConfirm(null);

        const route =
            action === 'approve'
                ? stockRoutes.restockRequests.approve({ restockRequest: row.id })
                : stockRoutes.restockRequests.reject({ restockRequest: row.id });
        router.patch(route.url, undefined, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Stock Requests" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Stock Requests" description="Review and dispatch restock requests from the godown." />

                <div className="flex flex-wrap gap-2">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => setStatus(t.key)}
                            className={
                                status === t.key
                                    ? 'bg-primary text-primary-foreground rounded-md px-3 py-1.5 text-sm font-medium'
                                    : 'text-muted-foreground hover:bg-muted rounded-md px-3 py-1.5 text-sm font-medium'
                            }
                        >
                            {t.label} <span className={status === t.key ? 'opacity-80' : 'text-muted-foreground'}>· {t.count}</span>
                        </button>
                    ))}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>From godown</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead className="text-right">Qty</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Transfer</TableHead>
                                <TableHead>Requested by</TableHead>
                                <TableHead className="text-right">Requested at</TableHead>
                                {status === 'pending' && <TableHead className="text-right">Actions</TableHead>}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {requests.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell>
                                        <div className="font-medium">{r.product?.name ?? '—'}</div>
                                        <div className="text-muted-foreground text-xs">
                                            #{r.id}
                                            {r.product_variant ? ` · ${r.product_variant.name}` : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell>{r.source_branch?.name ?? '—'}</TableCell>
                                    <TableCell>{r.branch?.name ?? '—'}</TableCell>
                                    <TableCell className="text-right tabular-nums">{Number(r.quantity)}</TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant[r.status] ?? 'secondary'}>{r.status}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        {r.transfer ? (
                                            <Link
                                                href={stockTransfersRoutes.show({ transfer: r.transfer.id }).url}
                                                className="text-primary text-sm font-medium hover:underline"
                                            >
                                                {r.transfer.transfer_no}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell>{r.requester?.name ?? '—'}</TableCell>
                                    <TableCell className="text-right text-muted-foreground">
                                        {formatDateTime(r.created_at)}
                                    </TableCell>
                                    {status === 'pending' ? (
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    className="h-7 text-xs"
                                                    onClick={() => review('approve', r)}
                                                >
                                                    Approve
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    className="h-7 text-xs"
                                                    variant="destructive"
                                                    onClick={() => review('reject', r)}
                                                >
                                                    Reject
                                                </Button>
                                            </div>
                                        </TableCell>
                                    ) : null}
                                </TableRow>
                            ))}
                            {requests.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={9} className="h-24 text-center text-muted-foreground">
                                        No {status} restock requests.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {requests.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {requests.current_page} of {requests.last_page} · {requests.total} total
                        </span>
                        <div className="flex gap-2">
                            {requests.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={requests.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {requests.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={requests.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={reviewConfirm !== null}
                onOpenChange={(o) => !o && setReviewConfirm(null)}
                title={reviewConfirm?.action === 'approve' ? 'Approve restock request' : 'Reject restock request'}
                description={
                    reviewConfirm ? (
                        <>
                            {reviewConfirm.action === 'approve' ? 'Dispatch ' : 'Reject '}
                            <strong>{Number(reviewConfirm.row.quantity)}</strong> units of{' '}
                            {reviewConfirm.row.product?.name ?? 'this product'} from{' '}
                            {reviewConfirm.row.source_branch?.name ?? 'the godown'} to{' '}
                            {reviewConfirm.row.branch?.name}?
                        </>
                    ) : undefined
                }
                confirmLabel={reviewConfirm?.action === 'approve' ? 'Approve & dispatch' : 'Reject'}
                destructive={reviewConfirm?.action === 'reject'}
                onConfirm={confirmReview}
            />
        </>
    );
}

StockRequests.layout = {
    breadcrumbs: [{ title: 'Stock Requests', href: stockRoutes.requests().url }],
};