import { Form, Head, Link, router } from '@inertiajs/react';
import CashDrawerController from '@/actions/App/Http/Controllers/CashDrawerController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { Textarea } from '@/components/ui/textarea';
import finance from '@/routes/finance';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useState } from 'react';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Movement = {
    id: number;
    type: 'cash_in' | 'cash_out' | 'safe_drop' | 'petty_cash';
    amount: string | number;
    reference: string | null;
    notes: string | null;
    status: string;
    branch: { id: number; name: string } | null;
    shift: { id: number; shift_no: string } | null;
    requester: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    requested_at: string;
    approved_at: string | null;
};

type ShiftSummary = {
    id: number;
    shift_no: string;
    status: string;
    opened_at: string;
    closed_at: string | null;
    opening_float: number;
    expected_in_drawer: number;
    difference: number;
    flagged: boolean;
    branch: string | null;
    cashier: string | null;
};

type Props = {
    movements: Paginated<Movement>;
    shifts: ShiftSummary[];
    threshold: number;
    can_request: boolean;
    can_approve: boolean;
    filters: { status?: string; type?: string };
};

const TYPE_LABELS: Record<string, string> = {
    cash_in: 'Cash In',
    cash_out: 'Cash Out',
    safe_drop: 'Safe Drop',
    petty_cash: 'Petty Cash',
};

export default function CashDrawerIndex({ movements, shifts, can_request, can_approve, filters }: Props) {
    const [requestOpen, setRequestOpen] = useState(false);

    const applyWith = (params: Record<string, string>) => {
        router.get(finance.drawer().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Cash Drawer" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Cash Drawer"
                        description="Track cash movements and review closed-shift drawer positions."
                    />
                    {can_request && (
                        <Dialog open={requestOpen} onOpenChange={setRequestOpen}>
                            <DialogTrigger asChild>
                                <Button>New cash movement</Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Cash movement</DialogTitle>
                                    <DialogDescription>
                                        Cash-ins are recorded immediately; cash-outs, safe drops
                                        and petty cash go to Finance for approval.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    {...CashDrawerController.store.form()}
                                    options={{ preserveScroll: true }}
                                    className="space-y-4"
                                    onSubmit={() => setRequestOpen(false)}
                                >
                                    {({ errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="type">Type</Label>
                                                <select
                                                    id="type"
                                                    name="type"
                                                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                                    defaultValue="cash_out"
                                                >
                                                    {Object.entries(TYPE_LABELS).map(
                                                        ([value, label]) => (
                                                            <option key={value} value={value}>
                                                                {label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="amount">Amount</Label>
                                                <Input
                                                    id="amount"
                                                    name="amount"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    required
                                                />
                                                <InputError message={errors?.amount} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="reference">Reference</Label>
                                                <Input id="reference" name="reference" />
                                                <InputError message={errors?.reference} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="notes">Notes</Label>
                                                <Textarea id="notes" name="notes" />
                                                <InputError message={errors?.notes} />
                                            </div>
                                            <Button type="submit">Submit</Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {shifts.length > 0 && (
                    <div>
                        <h2 className="mb-3 text-sm font-semibold">Recent closed shifts</h2>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {shifts.map((s) => (
                                <div
                                    key={s.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="font-mono text-sm font-medium">
                                            {s.shift_no}
                                        </span>
                                        <Badge
                                            variant={
                                                s.flagged ? 'destructive' : 'outline'
                                            }
                                        >
                                            {s.flagged ? 'Variance' : s.status}
                                        </Badge>
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {s.cashier} · {s.branch}
                                    </div>
                                    <dl className="mt-2 grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground">Expected</dt>
                                            <dd className="tabular-nums">
                                                {formatMoney(s.expected_in_drawer)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">Difference</dt>
                                            <dd
                                                className={
                                                    'tabular-nums ' +
                                                    (s.difference < 0
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : s.difference > 0
                                                          ? 'text-emerald-600 dark:text-emerald-400'
                                                          : '')
                                                }
                                            >
                                                {formatMoney(s.difference)}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="status-filter">Status</Label>
                        <select
                            id="status-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.status ?? ''}
                            onChange={(e) =>
                                applyWith({
                                    status: e.target.value === 'all' ? '' : e.target.value,
                                })
                            }
                        >
                            <option value="all">All</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="type-filter">Type</Label>
                        <select
                            id="type-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.type ?? ''}
                            onChange={(e) =>
                                applyWith({
                                    type: e.target.value === 'all' ? '' : e.target.value,
                                })
                            }
                        >
                            <option value="all">All types</option>
                            {Object.entries(TYPE_LABELS).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Type</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Shift</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Requested By</TableHead>
                                <TableHead>Requested At</TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {movements.data.map((m) => (
                                <TableRow key={m.id}>
                                    <TableCell>
                                        {TYPE_LABELS[m.type] ?? m.type}
                                    </TableCell>
                                    <TableCell>{m.branch?.name ?? '—'}</TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {m.shift?.shift_no ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(m.amount))}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                m.status === 'approved'
                                                    ? 'default'
                                                    : m.status === 'rejected'
                                                      ? 'destructive'
                                                      : 'outline'
                                            }
                                        >
                                            {m.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{m.requester?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(m.requested_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {can_approve && m.status === 'pending' && (
                                            <div className="flex justify-end gap-2">
                                                <Form
                                                    {...CashDrawerController.approve.form({ movement: m.id })}
                                                    options={{ preserveScroll: true }}
                                                >
                                                    <Button type="submit" size="sm">
                                                        Approve
                                                    </Button>
                                                </Form>
                                                <Form
                                                    {...CashDrawerController.reject.form({ movement: m.id })}
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
                            {movements.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No cash movements found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {movements.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {movements.current_page} of {movements.last_page} ·{' '}
                            {movements.total} total
                        </span>
                        <div className="flex gap-2">
                            {movements.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={movements.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {movements.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={movements.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

CashDrawerIndex.layout = {
    breadcrumbs: [{ title: 'Cash Drawer', href: finance.drawer().url }],
};