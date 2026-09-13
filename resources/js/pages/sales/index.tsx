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
import sales from '@/routes/sales';
import { formatDateTime, formatMoney } from '@/lib/format';

type SaleRow = {
    id: number;
    sale_no: string;
    status: string;
    payment_status: string;
    subtotal: string | number;
    total: string | number;
    amount_paid: string | number;
    change_amount: string | number;
    has_price_reduction: boolean;
    price_reduction_amount: string | number;
    created_at: string;
    branch: { name: string } | null;
    user: { name: string } | null;
    customer: { name: string } | null;
    items: { id: number }[];
};

type Props = {
    sales: {
        data: SaleRow[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { search?: string; status?: string; user_id?: string };
};

const statusVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    active: 'default',
    voided: 'destructive',
    returned: 'secondary',
};

export default function SalesIndex({ sales: salesList, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyWith = (params: Record<string, string>) => {
        router.get(sales.index().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Sales" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Sales"
                    description="Review completed transactions recorded at the POS terminal."
                />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            className="w-64"
                            placeholder="Sale no, receipt no…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) =>
                                e.key === 'Enter' && applyWith({ search })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status-filter">Status</Label>
                        <select
                            id="status-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.status ?? ''}
                            onChange={(e) =>
                                applyWith({
                                    status:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="voided">Voided</option>
                            <option value="returned">Returned</option>
                        </select>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() =>
                            applyWith({ search, status: filters.status ?? '' })
                        }
                    >
                        Apply
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Sale No.</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Cashier</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Sold At</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {salesList.data.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell className="font-mono text-sm font-medium">
                                        {s.sale_no}
                                    </TableCell>
                                    <TableCell>
                                        {s.branch?.name ?? '—'}
                                    </TableCell>
                                    <TableCell>{s.user?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        {s.customer?.name ?? 'Walk-in'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {s.items.length} lines
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {formatMoney(Number(s.total))}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {s.has_price_reduction &&
                                        Number(s.price_reduction_amount) > 0 ? (
                                            <span className="text-destructive">
                                                -
                                                {formatMoney(
                                                    Number(
                                                        s.price_reduction_amount,
                                                    ),
                                                )}
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                statusVariant[s.status] ??
                                                'outline'
                                            }
                                        >
                                            {s.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(s.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={sales.show.url({
                                                    sale: s.id,
                                                })}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {salesList.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={10}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No sales found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {salesList.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {salesList.current_page} of{' '}
                            {salesList.last_page} · {salesList.total} total
                        </span>
                        <div className="flex gap-2">
                            {salesList.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={salesList.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {salesList.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={salesList.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

SalesIndex.layout = {
    breadcrumbs: [{ title: 'Sales', href: sales.index().url }],
};
