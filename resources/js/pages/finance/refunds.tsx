import { Head, Link, router } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import RefundApprovalController from '@/actions/App/Http/Controllers/RefundApprovalController';
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
import finance from '@/routes/finance';
import { formatDateTime, formatMoney } from '@/lib/format';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type RefundRequest = {
    id: number;
    reason: string;
    amount: string | number;
    restock: boolean;
    status: string;
    requested_at: string;
    reviewed_at: string | null;
    sale: { id: number; sale_no: string } | null;
    branch: { id: number; name: string; currency: string } | null;
    initiator: { id: number; name: string } | null;
    reviewer: { id: number; name: string } | null;
};

type Props = {
    refunds: Paginated<RefundRequest>;
    filters: { status?: string };
};

export default function RefundsIndex({ refunds, filters }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(finance.refunds().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Refund Approvals" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Refund Approvals"
                    description="Approve, reject or review refunds raised by cashiers at the counter."
                />

                <div className="grid gap-2">
                    <Label htmlFor="status-filter">Status</Label>
                    <select
                        id="status-filter"
                        className="border-input h-9 max-w-xs rounded-md border bg-transparent px-3 text-sm"
                        defaultValue={filters.status ?? ''}
                        onChange={(e) =>
                            applyWith({
                                status: e.target.value === 'all' ? '' : e.target.value,
                            })
                        }
                    >
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="auto_approved">Auto-approved</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Sale</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Restock</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Requested By</TableHead>
                                <TableHead>Requested At</TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {refunds.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-mono text-sm">
                                        {r.sale?.sale_no ?? '—'}
                                    </TableCell>
                                    <TableCell>{r.branch?.name ?? '—'}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(r.amount))}
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate">
                                        {r.reason}
                                    </TableCell>
                                    <TableCell>{r.restock ? 'Yes' : 'No'}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                r.status === 'approved' ||
                                                r.status === 'auto_approved'
                                                    ? 'default'
                                                    : r.status === 'rejected'
                                                      ? 'destructive'
                                                      : 'outline'
                                            }
                                        >
                                            {r.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{r.initiator?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(r.requested_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {r.status === 'pending' && (
                                            <div className="flex justify-end gap-2">
                                                <Form
                                                    {...RefundApprovalController.approve.form({ refundRequest: r.id })}
                                                    options={{ preserveScroll: true }}
                                                >
                                                    <Button type="submit" size="sm">
                                                        Approve
                                                    </Button>
                                                </Form>
                                                <Form
                                                    {...RefundApprovalController.reject.form({ refundRequest: r.id })}
                                                    options={{ preserveScroll: true }}
                                                >
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="destructive"
                                                    >
                                                        Reject
                                                    </Button>
                                                </Form>
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {refunds.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={9}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No refund requests found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {refunds.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {refunds.current_page} of {refunds.last_page} · {refunds.total}{' '}
                            total
                        </span>
                        <div className="flex gap-2">
                            {refunds.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={refunds.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {refunds.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={refunds.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

RefundsIndex.layout = {
    breadcrumbs: [{ title: 'Refund Approvals', href: finance.refunds().url }],
};