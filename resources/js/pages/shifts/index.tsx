import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ShiftController from '@/actions/App/Http/Controllers/ShiftController';
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
import shifts from '@/routes/shifts';
import { formatDateTime, formatMoney } from '@/lib/format';

type Shift = {
    id: number;
    shift_no: string;
    status: string;
    opening_float: string | number;
    closing_count: string | number | null;
    expected_in_drawer: string | number;
    difference: string | number;
    opened_at: string;
    closed_at: string | null;
    user: { name: string };
    branch: { name: string };
    notes: string | null;
};

type Props = {
    shifts: {
        data: Shift[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { status?: string };
    activeShift: Shift | null;
    branch: { name: string };
    can: { open: boolean; close: boolean };
};

export default function ShiftsIndex({
    shifts: shiftList,
    filters,
    activeShift,
    branch,
    can,
}: Props) {
    const [openFormOpen, setOpenFormOpen] = useState(false);

    const applyWith = (params: Record<string, string>) => {
        router.get(shifts.index().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Shifts" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Shifts"
                        description="Track cash drawer openings and closings."
                    />
                    {can.open && !activeShift && (
                        <Dialog
                            open={openFormOpen}
                            onOpenChange={setOpenFormOpen}
                        >
                            <DialogTrigger asChild>
                                <Button>Open shift</Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Open a new shift</DialogTitle>
                                    <DialogDescription>
                                        Record the opening float for{' '}
                                        {branch.name}.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    {...ShiftController.open.form()}
                                    options={{ preserveScroll: true }}
                                    className="space-y-4"
                                    onSubmit={() => setOpenFormOpen(false)}
                                >
                                    {({ errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="opening_float">
                                                    Opening float
                                                </Label>
                                                <Input
                                                    id="opening_float"
                                                    name="opening_float"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors?.opening_float
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="notes">
                                                    Notes
                                                </Label>
                                                <Textarea
                                                    id="notes"
                                                    name="notes"
                                                />
                                                <InputError
                                                    message={errors?.notes}
                                                />
                                            </div>
                                            <Button type="submit">
                                                Open shift
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {activeShift && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            <Badge>Open</Badge>
                            <span className="font-medium">
                                {activeShift.shift_no}
                            </span>
                            <span className="text-muted-foreground text-sm">
                                Opened {formatDateTime(activeShift.opened_at)} ·{' '}
                                {activeShift.user?.name}
                            </span>
                        </div>
                        {can.close && (
                            <Form
                                {...ShiftController.close.form({
                                    shift: activeShift.id,
                                })}
                                options={{ preserveScroll: true }}
                                className="space-y-3"
                            >
                                {({ errors }) => (
                                    <>
                                        <div className="grid max-w-xs gap-2">
                                            <Label htmlFor="closing_count">
                                                Closing count
                                            </Label>
                                            <Input
                                                id="closing_count"
                                                name="closing_count"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                required
                                            />
                                            <InputError
                                                message={errors?.closing_count}
                                            />
                                        </div>
                                        <div className="grid max-w-sm gap-2">
                                            <Label htmlFor="notes">Notes</Label>
                                            <Textarea id="notes" name="notes" />
                                            <InputError
                                                message={errors?.notes}
                                            />
                                        </div>
                                        <Button type="submit">
                                            Close shift
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                )}

                <div className="flex items-end gap-3">
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
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Shift No.</TableHead>
                                <TableHead>Cashier</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Opening Float
                                </TableHead>
                                <TableHead className="text-right">
                                    Closing Count
                                </TableHead>
                                <TableHead className="text-right">
                                    Expected
                                </TableHead>
                                <TableHead className="text-right">
                                    Difference
                                </TableHead>
                                <TableHead>Opened At</TableHead>
                                <TableHead>Closed At</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {shiftList.data.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell className="font-mono text-sm font-medium">
                                        {s.shift_no}
                                    </TableCell>
                                    <TableCell>{s.user?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        {s.branch?.name ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                s.status === 'open'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {s.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(s.opening_float))}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {s.closing_count === null
                                            ? '—'
                                            : formatMoney(
                                                  Number(s.closing_count),
                                              )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(
                                            Number(s.expected_in_drawer),
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        <span
                                            className={
                                                Number(s.difference) < 0
                                                    ? 'text-red-600 dark:text-red-400'
                                                    : Number(s.difference) > 0
                                                      ? 'text-emerald-600 dark:text-emerald-400'
                                                      : 'text-muted-foreground'
                                            }
                                        >
                                            {formatMoney(Number(s.difference))}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(s.opened_at)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {s.closed_at
                                            ? formatDateTime(s.closed_at)
                                            : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {shiftList.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={10}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No shifts found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {shiftList.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {shiftList.current_page} of{' '}
                            {shiftList.last_page} · {shiftList.total} total
                        </span>
                        <div className="flex gap-2">
                            {shiftList.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={shiftList.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {shiftList.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={shiftList.next_page_url}>
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

ShiftsIndex.layout = {
    breadcrumbs: [{ title: 'Shifts', href: shifts.index().url }],
};
