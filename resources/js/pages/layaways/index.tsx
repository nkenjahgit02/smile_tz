import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import layaways from '@/routes/layaways';
import { formatDate, formatMoney } from '@/lib/format';

type Layaway = {
    id: number;
    total_amount: string | number;
    deposit_amount: string | number;
    paid_amount: string | number;
    balance: string | number;
    status: string;
    due_date: string;
    customer: { name: string; phone: string | null } | null;
    sale: { sale_no: string } | null;
    branch: { name: string } | null;
    created_at: string;
};

type Props = {
    layaways: {
        data: Layaway[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { status?: string };
};

const statusVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    active: 'default',
    fulfilled: 'outline',
    cancelled: 'destructive',
};

export default function LayawaysIndex({ layaways: layawayList }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(layaways.index().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Layaways" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Layaways"
                    description="Sales held on deposit until the balance is settled."
                />

                <div className="grid gap-2">
                    <Label htmlFor="status-filter">Status</Label>
                    <select
                        id="status-filter"
                        className="border-input h-9 w-48 rounded-md border bg-transparent px-3 text-sm"
                        defaultValue={status}
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
                        <option value="fulfilled">Fulfilled</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Customer</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Sale</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead className="text-right">
                                    Paid
                                </TableHead>
                                <TableHead className="text-right">
                                    Balance
                                </TableHead>
                                <TableHead>Due Date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {layawayList.data.map((l) => (
                                <TableRow key={l.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {l.customer?.name ?? '—'}
                                        </div>
                                        {l.customer?.phone && (
                                            <div className="text-muted-foreground text-xs">
                                                {l.customer.phone}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {l.branch?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {l.sale?.sale_no ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(l.total_amount))}
                                    </TableCell>
                                    <TableCell className="text-right text-emerald-600 tabular-nums dark:text-emerald-400">
                                        {formatMoney(Number(l.paid_amount))}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(l.balance))}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(l.due_date)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                statusVariant[l.status] ??
                                                'outline'
                                            }
                                        >
                                            {l.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={layaways.show.url({
                                                    layaway: l.id,
                                                })}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {layawayList.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={9}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No layaways found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {layawayList.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {layawayList.current_page} of{' '}
                            {layawayList.last_page} · {layawayList.total} total
                        </span>
                        <div className="flex gap-2">
                            {layawayList.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={layawayList.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {layawayList.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={layawayList.next_page_url}>
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

LayawaysIndex.layout = {
    breadcrumbs: [{ title: 'Layaways', href: layaways.index().url }],
};
