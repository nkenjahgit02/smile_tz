import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import LayawayController from '@/actions/App/Http/Controllers/LayawayController';
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
import layaways from '@/routes/layaways';
import { formatDateTime, formatMoney } from '@/lib/format';

type LayawayPayment = {
    id: number;
    amount: string | number;
    method: string;
    reference: string | null;
    created_at: string;
    receiver: { name: string } | null;
};

type SaleItem = {
    id: number;
    product: { name: string } | null;
    quantity: number;
    line_total: string | number;
};

type Layaway = {
    id: number;
    total_amount: string | number;
    deposit_amount: string | number;
    paid_amount: string | number;
    balance: string | number;
    status: string;
    due_date: string;
    created_at: string;
    customer: { name: string; phone: string | null } | null;
    branch: { name: string } | null;
    sale: { sale_no: string; items: SaleItem[] } | null;
    payments: LayawayPayment[];
    creator: { name: string } | null;
};

type Props = {
    layaway: Layaway;
    can_manage: boolean;
};

const statusVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    active: 'default',
    fulfilled: 'outline',
    cancelled: 'destructive',
};

export default function LayawaysShow({ layaway, can_manage }: Props) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const isActive = layaway.status === 'active';

    return (
        <>
            <Head title={`Layaway #${layaway.id}`} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={`Layaway #${layaway.id}`}
                        description={layaway.customer?.name ?? ''}
                    />
                    <div className="flex flex-wrap gap-2">
                        {can_manage && isActive && (
                            <Dialog
                                open={cancelOpen}
                                onOpenChange={setCancelOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        Cancel layaway
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Cancel layaway
                                        </DialogTitle>
                                        <DialogDescription>
                                            Provide a reason for cancelling this
                                            layaway.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...LayawayController.cancel.form({
                                            layaway: layaway.id,
                                        })}
                                        options={{ preserveScroll: true }}
                                        className="space-y-4"
                                        onSubmit={() => setCancelOpen(false)}
                                    >
                                        {({ errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="reason">
                                                        Reason
                                                    </Label>
                                                    <Input
                                                        id="reason"
                                                        name="reason"
                                                        placeholder="Why is this layaway being cancelled?"
                                                    />
                                                    <InputError
                                                        message={errors?.reason}
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                >
                                                    Confirm cancellation
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={layaways.index().url}>
                                Back to layaways
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={statusVariant[layaway.status] ?? 'outline'}>
                        {layaway.status}
                    </Badge>
                    <span className="text-muted-foreground text-sm">
                        Created {formatDateTime(layaway.created_at)} by{' '}
                        {layaway.creator?.name ?? '—'}
                    </span>
                </div>

                <div className="flex flex-wrap gap-3">
                    <div className="min-w-44 flex-1 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium">
                            Total
                        </p>
                        <p className="text-xl font-semibold tabular-nums">
                            {formatMoney(Number(layaway.total_amount))}
                        </p>
                    </div>
                    <div className="min-w-44 flex-1 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium">
                            Deposit
                        </p>
                        <p className="text-xl font-semibold tabular-nums">
                            {formatMoney(Number(layaway.deposit_amount))}
                        </p>
                    </div>
                    <div className="min-w-44 flex-1 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium">
                            Paid
                        </p>
                        <p className="text-xl font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                            {formatMoney(Number(layaway.paid_amount))}
                        </p>
                    </div>
                    <div className="min-w-44 flex-1 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium">
                            Balance
                        </p>
                        <p className="text-xl font-semibold tabular-nums">
                            {formatMoney(Number(layaway.balance))}
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="mb-3 text-sm font-semibold">Details</div>
                    <dl className="grid gap-2 text-sm sm:grid-cols-2">
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Customer</dt>
                            <dd className="text-right font-medium">
                                {layaway.customer?.name ?? '—'}
                                {layaway.customer?.phone
                                    ? ` (${layaway.customer.phone})`
                                    : ''}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Branch</dt>
                            <dd className="text-right font-medium">
                                {layaway.branch?.name ?? '—'}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Sale</dt>
                            <dd className="text-right font-mono">
                                {layaway.sale?.sale_no ?? '—'}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Due date</dt>
                            <dd className="text-right font-medium">
                                {formatDateTime(layaway.due_date)}
                            </dd>
                        </div>
                    </dl>
                </div>

                {layaway.sale && layaway.sale.items.length > 0 && (
                    <div className="rounded-xl border">
                        <div className="border-b px-4 py-3 text-sm font-semibold">
                            Items held
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead className="text-right">
                                        Qty
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Line Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {layaway.sale.items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            {item.product?.name ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {Number(item.quantity)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(
                                                Number(item.line_total),
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <div className="rounded-xl border">
                    <div className="border-b px-4 py-3 text-sm font-semibold">
                        Payment history
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Method</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead>Received By</TableHead>
                                <TableHead>Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {layaway.payments.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {p.method}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(p.amount))}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {p.reference ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {p.receiver?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(p.created_at)}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {layaway.payments.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No payments recorded yet.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {can_manage && isActive && (
                    <div className="rounded-xl border p-4">
                        <div className="mb-3 text-sm font-semibold">
                            Record a payment
                        </div>
                        <Form
                            {...LayawayController.pay.form({
                                layaway: layaway.id,
                            })}
                            options={{ preserveScroll: true }}
                            className="grid max-w-xl gap-3 sm:grid-cols-3"
                        >
                            {({ errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="amount">Amount</Label>
                                        <Input
                                            id="amount"
                                            name="amount"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            max={Number(layaway.balance)}
                                            required
                                        />
                                        <InputError message={errors?.amount} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="method">Method</Label>
                                        <select
                                            id="method"
                                            name="method"
                                            defaultValue="cash"
                                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="mobile">
                                                Mobile money
                                            </option>
                                            <option value="bank_transfer">
                                                Bank transfer
                                            </option>
                                        </select>
                                        <InputError message={errors?.method} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="reference">
                                            Reference
                                        </Label>
                                        <Input
                                            id="reference"
                                            name="reference"
                                            placeholder="Optional"
                                        />
                                    </div>
                                    <div className="sm:col-span-3">
                                        <Button type="submit">
                                            Record payment
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                )}
            </div>
        </>
    );
}

LayawaysShow.layout = {
    breadcrumbs: [{ title: 'Layaways', href: layaways.index().url }],
};
