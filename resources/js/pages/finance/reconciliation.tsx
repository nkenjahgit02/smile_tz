import { Form, Head, Link, router } from '@inertiajs/react';
import ReconciliationController from '@/actions/App/Http/Controllers/ReconciliationController';
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

type Shift = {
    id: number;
    shift_no: string;
    status: string;
    opened_at: string;
    closed_at: string | null;
    opening_float: string | number;
    closing_count: string | number | null;
    expected_in_drawer: string | number;
    difference: string | number;
    branch: { id: number; name: string } | null;
    user: { id: number; name: string } | null;
    reviewer: { id: number; name: string } | null;
};

type Props = {
    shifts: Paginated<Shift>;
    threshold: number;
    can_approve: boolean;
    filters: { status?: string; flagged?: string };
};

export default function ReconciliationIndex({ shifts, threshold, can_approve, filters }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(finance.reconciliation().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Shift Reconciliation" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Shift Reconciliation"
                    description={`Authenticate closed shifts and review drawer variances against the ${formatMoney(threshold)} threshold.`}
                />

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
                            <option value="closed">Closed</option>
                            <option value="reconciled">Reconciled</option>
                        </select>
                    </div>
                    <label className="flex h-9 items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            defaultChecked={filters.flagged === '1'}
                            onChange={(e) =>
                                applyWith({ flagged: e.target.checked ? '1' : '' })
                            }
                        />
                        Flagged only
                    </label>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Shift</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Cashier</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Float</TableHead>
                                <TableHead className="text-right">Expected</TableHead>
                                <TableHead className="text-right">Closing</TableHead>
                                <TableHead className="text-right">Difference</TableHead>
                                <TableHead>Closed At</TableHead>
                                <TableHead>Reviewed By</TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {shifts.data.map((s) => {
                                const diff = Number(s.difference);
                                const flagged = Math.abs(diff) > threshold;

                                return (
                                    <TableRow key={s.id}>
                                        <TableCell className="font-mono text-sm font-medium">
                                            {s.shift_no}
                                        </TableCell>
                                        <TableCell>{s.branch?.name ?? '—'}</TableCell>
                                        <TableCell>{s.user?.name ?? '—'}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    s.status === 'reconciled'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {s.status}
                                            </Badge>
                                            {flagged && (
                                                <Badge variant="destructive" className="ml-2">
                                                    Flagged
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(Number(s.opening_float))}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(Number(s.expected_in_drawer))}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {s.closing_count === null
                                                ? '—'
                                                : formatMoney(Number(s.closing_count))}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            <span
                                                className={
                                                    diff < 0
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : diff > 0
                                                          ? 'text-emerald-600 dark:text-emerald-400'
                                                          : 'text-muted-foreground'
                                                }
                                            >
                                                {formatMoney(diff)}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {s.closed_at ? formatDateTime(s.closed_at) : '—'}
                                        </TableCell>
                                        <TableCell>{s.reviewer?.name ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            {can_approve && s.status === 'closed' && (
                                                <Form
                                                    {...ReconciliationController.approve.form({ shift: s.id })}
                                                    options={{ preserveScroll: true }}
                                                >
                                                    <Button type="submit" size="sm">
                                                        Approve
                                                    </Button>
                                                </Form>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                            {shifts.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={11}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No closed shifts found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {shifts.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {shifts.current_page} of {shifts.last_page} · {shifts.total}{' '}
                            total
                        </span>
                        <div className="flex gap-2">
                            {shifts.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={shifts.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {shifts.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={shifts.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ReconciliationIndex.layout = {
    breadcrumbs: [{ title: 'Reconciliation', href: finance.reconciliation().url }],
};