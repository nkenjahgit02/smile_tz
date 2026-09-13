import { Form, Head } from '@inertiajs/react';
import AccountingController from '@/actions/App/Http/Controllers/AccountingController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { exportMethod as accountingExport } from '@/routes/finance/accounting';
import finance from '@/routes/finance';
import { formatDate, formatMoney } from '@/lib/format';
import { router } from '@inertiajs/react';

type Receivable = {
    id: number;
    name: string;
    phone: string | null;
    credit_balance: number;
    credit_limit: number;
    overdue: boolean;
    last_activity: string | null;
};

type Invoice = {
    id: number;
    supplier: string | null;
    invoice_no: string;
    amount: number;
    paid_amount: number;
    balance: number;
    due_date: string | null;
    status: string;
    overdue: boolean;
};

type Props = {
    receivables_total: number;
    payables_total: number;
    receivables: Receivable[];
    invoices: Invoice[];
    overdue_days: number;
    filters: { view?: string };
};

const STATUS_LABELS: Record<string, string> = {
    outstanding: 'Outstanding',
    partial: 'Partial',
    paid: 'Paid',
};

export default function AccountingIndex({ receivables_total, payables_total, receivables, invoices, overdue_days }: Props) {
    return (
        <>
            <Head title="Accounting (AR/AP)" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Accounting (AR/AP)"
                        description={`Outstanding customer credit and supplier invoices. Overdue is ${overdue_days} days past the credit/last activity date.`}
                    />
                    <Button variant="outline" asChild>
                        <a href={accountingExport().url} download>
                            Export CSV
                        </a>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-sm">Accounts receivable</div>
                        <div className="text-2xl font-semibold tabular-nums">
                            {formatMoney(receivables_total)}
                        </div>
                        <div className="text-muted-foreground text-sm">
                            {receivables.length} customer(s) with credit
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-sm">Accounts payable</div>
                        <div className="text-2xl font-semibold tabular-nums">
                            {formatMoney(payables_total)}
                        </div>
                        <div className="text-muted-foreground text-sm">
                            {invoices.length} outstanding invoice(s)
                        </div>
                    </div>
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-semibold">Accounts receivable</h2>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead className="text-right">Credit balance</TableHead>
                                    <TableHead className="text-right">Limit</TableHead>
                                    <TableHead>Last activity</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receivables.map((c) => (
                                    <TableRow key={c.id}>
                                        <TableCell className="font-medium">{c.name}</TableCell>
                                        <TableCell>{c.phone ?? '—'}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(c.credit_balance)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(c.credit_limit)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {formatDate(c.last_activity)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={c.overdue ? 'destructive' : 'outline'}
                                            >
                                                {c.overdue ? 'Overdue' : 'Current'}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {receivables.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-muted-foreground h-16 text-center"
                                        >
                                            No outstanding customer credit.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-semibold">Accounts payable</h2>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Supplier</TableHead>
                                    <TableHead>Invoice</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead className="text-right">Paid</TableHead>
                                    <TableHead className="text-right">Balance</TableHead>
                                    <TableHead>Due date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Payment</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.map((inv) => (
                                    <TableRow key={inv.id}>
                                        <TableCell>{inv.supplier ?? '—'}</TableCell>
                                        <TableCell className="font-mono text-sm">
                                            {inv.invoice_no}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(inv.amount)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(inv.paid_amount)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(inv.balance)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {formatDate(inv.due_date)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    inv.status === 'paid'
                                                        ? 'default'
                                                        : inv.overdue
                                                          ? 'destructive'
                                                          : 'outline'
                                                }
                                            >
                                                {inv.overdue && inv.status !== 'paid'
                                                    ? 'Overdue'
                                                    : STATUS_LABELS[inv.status] ?? inv.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {inv.balance > 0 && (
                                                <PaymentDialog invoice={inv} />
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {invoices.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="text-muted-foreground h-16 text-center"
                                        >
                                            No outstanding supplier invoices.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(finance.accounting().url)}
                    >
                        Refresh
                    </Button>
                </div>
            </div>
        </>
    );
}

function PaymentDialog({ invoice }: { invoice: Invoice }) {
    return (
        <Form
            {...AccountingController.payInvoice.form({ invoice: invoice.id })}
            options={{ preserveScroll: true }}
        >
            <label className="mb-1 block text-left text-xs text-muted-foreground">
                Amount
            </label>
            <div className="flex gap-2">
                <Input
                    name="amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    max={invoice.balance}
                    className="h-8 w-28"
                    required
                />
                <Button type="submit" size="sm">
                    Pay
                </Button>
            </div>
        </Form>
    );
}

AccountingIndex.layout = {
    breadcrumbs: [{ title: 'Accounting (AR/AP)', href: finance.accounting().url }],
};