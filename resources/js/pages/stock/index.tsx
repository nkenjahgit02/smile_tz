import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import stockRoutes from '@/routes/stock';
import { formatDateTime } from '@/lib/format';

type Row = {
    id: number;
    quantity: string;
    reserved: string;
    expiring_qty: string;
    branch: { id: number; name: string; type: string } | null;
    product: { id: number; name: string; sku: string; barcode: string | null; reorder_level: number } | null;
    variant: { id: number; name: string } | null;
};

type RestockRow = {
    id: number;
    quantity: string;
    status: string;
    notes: string | null;
    created_at: string;
    product: { id: number; name: string; sku: string } | null;
    product_variant: { id: number; name: string } | null;
    branch: { id: number; name: string; type: string } | null;
    source_branch: { id: number; name: string; type: string } | null;
    requester: { id: number; name: string } | null;
};

type Props = {
    stock: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    restocks: RestockRow[];
    canApprove: boolean;
    branches: { id: number; name: string }[];
    defaultBranch: number | null;
    filters: { branch_id?: string; alert_type?: string; search?: string; view?: string };
};

const statusVariant: Record<string, 'outline' | 'secondary' | 'destructive' | 'default'> = {
    pending: 'outline',
    dispatched: 'secondary',
    received: 'default',
    rejected: 'destructive',
};

export default function StockIndex({ stock, restocks, canApprove, branches, defaultBranch, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [view, setView] = useState<'stock' | 'requests'>(filters.view === 'requests' ? 'requests' : 'stock');
    const [requestConfirm, setRequestConfirm] = useState<Row | null>(null);
    const [reviewConfirm, setReviewConfirm] = useState<{ action: 'approve' | 'reject'; row: RestockRow } | null>(null);

    const applyWith = (params: Record<string, string>) => {
        router.get(stockRoutes.index().url, params, { preserveState: true, preserveScroll: true });
    };

    const alertTabs = [
        { key: '', label: 'All' },
        { key: 'low', label: 'Low stock' },
        { key: 'out', label: 'Out of stock' },
        { key: 'expiring', label: 'Expiring' },
    ];

    const pendingKeys = new Set(
        restocks
            .filter((r) => r.status === 'pending')
            .map((r) => `${r.branch?.id}:${r.product?.id}:${r.product_variant?.id ?? ''}`),
    );

    const requestRestock = (row: Row) => {
        const qty = Number(row.quantity);
        const suggested = Math.max((row.product?.reorder_level ?? 0) - qty, 0);
        if (suggested <= 0) return;
        if (!row.product || !row.branch) return;

        setRequestConfirm(row);
    };

    const confirmRequestRestock = () => {
        const row = requestConfirm;
        if (!row) return;
        setRequestConfirm(null);

        const qty = Math.max((row.product?.reorder_level ?? 0) - Number(row.quantity), 0);
        router.post(
            stockRoutes.restockRequests.store().url,
            {
                product_id: row.product?.id,
                product_variant_id: row.variant?.id ?? null,
                branch_id: row.branch?.id,
                quantity: qty,
            },
            { preserveScroll: true },
        );
    };

    const review = (action: 'approve' | 'reject', row: RestockRow) => {
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
            <Head title="Stock" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Stock" description="On-hand quantities by product and branch." />

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                        {alertTabs.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => {
                                    setView('stock');
                                    applyWith(
                                        t.key === '' && (filters.alert_type ?? '') !== ''
                                            ? { search: filters.search ?? '' }
                                            : { alert_type: t.key, search: filters.search ?? '' },
                                    );
                                }}
                                className={
                                    view === 'stock' && (filters.alert_type ?? '') === t.key
                                        ? 'bg-primary text-primary-foreground rounded-md px-3 py-1.5 text-sm font-medium'
                                        : 'text-muted-foreground hover:bg-muted rounded-md px-3 py-1.5 text-sm font-medium'
                                }
                            >
                                {t.label}
                            </button>
                        ))}
                        <button
                            onClick={() => setView('requests')}
                            className={
                                view === 'requests'
                                    ? 'bg-primary text-primary-foreground rounded-md px-3 py-1.5 text-sm font-medium'
                                    : 'text-muted-foreground hover:bg-muted rounded-md px-3 py-1.5 text-sm font-medium'
                            }
                        >
                            Restock requests
                        </button>
                    </div>
                    {view === 'stock' && (
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="search">Search</Label>
                                <Input
                                    id="search"
                                    className="w-52"
                                    placeholder="Name, SKU, barcode…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyWith({ search, alert_type: filters.alert_type ?? '' })}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="branch-filter">Branch</Label>
                                <select
                                    id="branch-filter"
                                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                    defaultValue={filters.branch_id ?? defaultBranch ?? ''}
                                    onChange={(e) =>
                                        applyWith({ branch_id: e.target.value === '' ? '' : e.target.value })
                                    }
                                >
                                    <option value="">My branch</option>
                                    {branches.map((b) => (
                                        <option key={b.id} value={String(b.id)}>
                                            {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    )}
                </div>

                {view === 'requests' ? (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead>From godown</TableHead>
                                    <TableHead>Branch</TableHead>
                                    <TableHead className="text-right">Qty</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Requested by</TableHead>
                                    <TableHead className="text-right">Requested at</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {restocks.map((r) => (
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
                                        <TableCell>{r.requester?.name ?? '—'}</TableCell>
                                        <TableCell className="text-right text-muted-foreground">
                                            {formatDateTime(r.created_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {r.status === 'pending' && canApprove ? (
                                                <div className="flex justify-end gap-2">
                                                    <Button size="sm" className="h-7 text-xs" onClick={() => review('approve', r)}>
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
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {restocks.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={8} className="h-24 text-center text-muted-foreground">
                                            No restock requests yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Branch</TableHead>
                                    <TableHead className="text-right">On hand</TableHead>
                                    <TableHead className="text-right">Reserved</TableHead>
                                    <TableHead className="text-right">Expiring (30d)</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stock.data.map((row) => {
                                    const qty = Number(row.quantity);
                                    const reorder = row.product?.reorder_level ?? 0;
                                    const isLow = qty <= reorder;
                                    const suggested = Math.max(reorder - qty, 0);
                                    const hasPending = !!row.product && pendingKeys.has(`${row.branch?.id}:${row.product.id}:${row.variant?.id ?? ''}`);
                                    const isGodown = row.branch?.type === 'godown';
                                    const canRequest = isLow && suggested > 0 && !hasPending && !isGodown;
                                    return (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <div className="font-medium">{row.product?.name}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {row.product?.sku}
                                                    {row.variant ? ` · ${row.variant.name}` : ''}
                                                </div>
                                            </TableCell>
                                            <TableCell>{row.branch?.name ?? '—'}</TableCell>
                                            <TableCell className="text-right tabular-nums">{qty}</TableCell>
                                            <TableCell className="text-right tabular-nums">{Number(row.reserved)}</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {Number(row.expiring_qty) > 0 ? (
                                                    <Badge variant="destructive">{row.expiring_qty}</Badge>
                                                ) : (
                                                    '0'
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {qty <= 0 ? (
                                                    <Badge variant="destructive">Out of stock</Badge>
                                                ) : isLow ? (
                                                    <Badge variant="outline">Low</Badge>
                                                ) : (
                                                    <Badge variant="secondary">OK</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {isGodown ? (
                                                    '—'
                                                ) : isLow ? (
                                                    hasPending ? (
                                                        <span className="text-muted-foreground text-xs">Pending</span>
                                                    ) : suggested > 0 ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 text-xs"
                                                            onClick={() => requestRestock(row)}
                                                        >
                                                            Request restock
                                                        </Button>
                                                    ) : (
                                                        '—'
                                                    )
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                                {stock.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            No stock rows found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {view === 'stock' && stock.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {stock.current_page} of {stock.last_page} · {stock.total} total
                        </span>
                        <div className="flex gap-2">
                            {stock.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={stock.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {stock.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={stock.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={requestConfirm !== null}
                onOpenChange={(o) => !o && setRequestConfirm(null)}
                title="Request restock"
                description={
                    requestConfirm && requestConfirm.product ? (
                        <>
                            Request{' '}
                            <strong>
                                {Math.max((requestConfirm.product?.reorder_level ?? 0) - Number(requestConfirm.quantity), 0)}{' '}
                                units
                            </strong>{' '}
                            of {requestConfirm.product.name}
                            {requestConfirm.variant ? ` (${requestConfirm.variant.name})` : ''} from the godown to{' '}
                            {requestConfirm.branch?.name}?
                        </>
                    ) : undefined
                }
                confirmLabel="Send request"
                onConfirm={confirmRequestRestock}
            />

            <ConfirmDialog
                open={reviewConfirm !== null}
                onOpenChange={(o) => !o && setReviewConfirm(null)}
                title={reviewConfirm?.action === 'approve' ? 'Approve restock request' : 'Reject restock request'}
                description={
                    reviewConfirm ? (
                        <>
                            {reviewConfirm.action === 'approve'
                                ? 'Dispatch '
                                : 'Reject '}{' '}
                            <strong>{Number(reviewConfirm.row.quantity)}</strong> units of{' '}
                            {reviewConfirm.row.product?.name ?? 'this product'} to {reviewConfirm.row.branch?.name}?
                            {reviewConfirm.action === 'approve' && reviewConfirm.row.source_branch ? (
                                <>
                                    <div className="mt-2 text-sm">
                                        Stock will be dispatched from{' '}
                                        <strong>{reviewConfirm.row.source_branch.name}</strong>.
                                    </div>
                                </>
                            ) : null}
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

StockIndex.layout = {
    breadcrumbs: [{ title: 'Stock', href: stockRoutes.index().url }],
};