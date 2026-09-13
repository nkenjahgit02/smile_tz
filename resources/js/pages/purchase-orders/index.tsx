import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
import purchaseOrders from '@/routes/purchase-orders';
import { formatDate } from '@/lib/format';

type Supplier = { id: number; name: string };
type OrderRow = {
    id: number;
    order_no: string;
    status: string;
    expected_at: string | null;
    received_at: string | null;
    created_at: string;
    supplier: { id: number; name: string };
    branch: { id: number; name: string };
    items: { id: number; product: { id: number; name: string } | null }[];
};

type Props = {
    orders: {
        data: OrderRow[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    suppliers: Supplier[];
    filters: { status?: string; supplier_id?: string; search?: string };
};

const statusVariant: Record<string, 'outline' | 'secondary' | 'destructive' | 'default'> = {
    draft: 'outline',
    approved: 'secondary',
    received: 'default',
    cancelled: 'destructive',
};

export default function PurchaseOrdersIndex({ orders, suppliers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyWith = (params: Record<string, string>) => {
        router.get(purchaseOrders.index().url, params, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Purchase Orders" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Purchase Orders"
                        description="Order stock from suppliers and receive it into inventory."
                    />
                    <Button asChild>
                        <Link href={purchaseOrders.create().url}>New purchase order</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            className="w-52"
                            placeholder="PO number…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyWith({ search })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status-filter">Status</Label>
                        <select
                            id="status-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.status ?? ''}
                            onChange={(e) => applyWith({ status: e.target.value === 'all' ? '' : e.target.value })}
                        >
                            <option value="all">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="supplier-filter">Supplier</Label>
                        <select
                            id="supplier-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.supplier_id ?? ''}
                            onChange={(e) => applyWith({ supplier_id: e.target.value === 'all' ? '' : e.target.value })}
                        >
                            <option value="all">All suppliers</option>
                            {suppliers.map((s) => (
                                <option key={s.id} value={String(s.id)}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order No.</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead>Expected</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.data.map((o) => (
                                <TableRow key={o.id}>
                                    <TableCell className="font-mono text-sm font-medium">
                                        {o.order_no}
                                    </TableCell>
                                    <TableCell>{o.supplier?.name ?? '—'}</TableCell>
                                    <TableCell>{o.branch?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{o.items.length} lines</Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {o.expected_at ? formatDate(o.expected_at) : '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant[o.status] ?? 'outline'}>{o.status}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={purchaseOrders.show.url({ order: o.id })}>View</Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {orders.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                        No purchase orders found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {orders.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {orders.current_page} of {orders.last_page} · {orders.total} total
                        </span>
                        <div className="flex gap-2">
                            {orders.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={orders.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {orders.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={orders.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

PurchaseOrdersIndex.layout = {
    breadcrumbs: [{ title: 'Purchase Orders', href: purchaseOrders.index().url }],
};