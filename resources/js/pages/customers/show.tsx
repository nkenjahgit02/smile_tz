import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
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
import customerRoutes from '@/routes/customers';
import { formatDateTime, formatMoney } from '@/lib/format';

type Customer = {
    id: number;
    customer_no: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    customer_type: string;
    is_active: boolean;
    loyalty_tier: string;
    loyalty_points: number;
    lifetime_points: number;
    lifetime_spend: string | number;
    deposit_balance: string | number;
    credit_balance: string | number;
    credit_limit: string | number;
    created_at: string;
};

type CustomerTransaction = {
    id: number;
    type: string;
    description: string | null;
    amount: string | number;
    points_change: number | null;
    running_credit: string | number | null;
    running_deposit: string | number | null;
    running_points: number | null;
    created_at: string;
    reference_type: string | null;
    reference_id: number | null;
    creator: { name: string } | null;
};

type Props = {
    customer: Customer;
    transactions: CustomerTransaction[];
    can: { manage: boolean; credit: boolean; loyalty: boolean };
};

const typeVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    walk_in: 'outline',
    member: 'default',
    credit: 'secondary',
};

const tierVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    bronze: 'outline',
    silver: 'secondary',
    gold: 'default',
    platinum: 'destructive',
};

export default function CustomersShow({ customer, transactions, can }: Props) {
    const [creditOpen, setCreditOpen] = useState(false);
    const [depositOpen, setDepositOpen] = useState(false);
    const [pointsOpen, setPointsOpen] = useState(false);

    const availableCredit = Math.max(
        Number(customer.credit_limit) - Number(customer.credit_balance),
        0,
    );

    return (
        <>
            <Head title={customer.name} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={customer.name}
                        description={`${customer.customer_no} · ${customer.phone ?? customer.email ?? 'no contact'}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        {can.credit && Number(customer.credit_balance) > 0 && (
                            <ActionDialog
                                open={creditOpen}
                                onOpenChange={setCreditOpen}
                                title="Collect credit payment"
                                description="Record a payment received against this customer's outstanding credit."
                            >
                                <Form
                                    {...CustomerController.collectCredit.form({ customer: customer.id })}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-4"
                                    onSubmit={() => setCreditOpen(false)}
                                >
                                    {({ errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="amount">
                                                    Amount
                                                </Label>
                                                <Input
                                                    id="amount"
                                                    name="amount"
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    max={Number(customer.credit_balance)}
                                                    required
                                                />
                                                <InputError message={errors?.amount} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="method">
                                                    Method
                                                </Label>
                                                <select
                                                    id="method"
                                                    name="method"
                                                    defaultValue="cash"
                                                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                                >
                                                    <option value="cash">Cash</option>
                                                    <option value="card">Card</option>
                                                    <option value="mobile">Mobile money</option>
                                                    <option value="bank_transfer">Bank transfer</option>
                                                    <option value="deposit">Deposit balance</option>
                                                </select>
                                                <InputError message={errors?.method} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="reference">
                                                    Reference
                                                </Label>
                                                <Input id="reference" name="reference" placeholder="Optional" />
                                            </div>
                                            <Button type="submit">
                                                Record payment
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </ActionDialog>
                        )}
                        {can.manage && (
                            <ActionDialog
                                open={depositOpen}
                                onOpenChange={setDepositOpen}
                                title="Add deposit"
                                description="Put money on account for this customer's deposit / wallet balance."
                            >
                                <Form
                                    {...CustomerController.deposit.form({ customer: customer.id })}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-4"
                                    onSubmit={() => setDepositOpen(false)}
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
                                                    required
                                                />
                                                <InputError message={errors?.amount} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="notes">Notes</Label>
                                                <Input id="notes" name="notes" placeholder="Optional" />
                                            </div>
                                            <Button type="submit">
                                                Add deposit
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </ActionDialog>
                        )}
                        {can.loyalty && (
                            <ActionDialog
                                open={pointsOpen}
                                onOpenChange={setPointsOpen}
                                title="Adjust loyalty points"
                                description="Manually add or remove points (use a negative value to remove)."
                            >
                                <Form
                                    {...CustomerController.adjustPoints.form({ customer: customer.id })}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-4"
                                    onSubmit={() => setPointsOpen(false)}
                                >
                                    {({ errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="points">Points</Label>
                                                <Input
                                                    id="points"
                                                    name="points"
                                                    type="number"
                                                    step="1"
                                                    required
                                                />
                                                <InputError message={errors?.points} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="reason">Reason</Label>
                                                <Input id="reason" name="reason" required />
                                                <InputError message={errors?.reason} />
                                            </div>
                                            <Button type="submit">
                                                Save points
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </ActionDialog>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={customerRoutes.index().url}>All customers</Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={typeVariant[customer.customer_type] ?? 'outline'}>
                        {customer.customer_type}
                    </Badge>
                    <Badge variant={tierVariant[customer.loyalty_tier] ?? 'outline'}>
                        {customer.loyalty_tier}
                    </Badge>
                    {customer.is_active ? (
                        <Badge variant="outline">Active</Badge>
                    ) : (
                        <Badge variant="destructive">Inactive</Badge>
                    )}
                    <span className="text-muted-foreground text-sm">
                        Since {formatDateTime(customer.created_at)}
                    </span>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium uppercase">
                            Loyalty points
                        </p>
                        <p className="text-xl font-semibold tabular-nums">
                            {customer.loyalty_points}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            {customer.lifetime_points} lifetime
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium uppercase">
                            Deposit balance
                        </p>
                        <p className="text-xl font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                            {formatMoney(Number(customer.deposit_balance))}
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium uppercase">
                            Outstanding credit
                        </p>
                        <p className="text-xl font-semibold text-amber-600 tabular-nums dark:text-amber-400">
                            {formatMoney(Number(customer.credit_balance))}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            of {formatMoney(Number(customer.credit_limit))} limit ·{' '}
                            {formatMoney(availableCredit)} available
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium uppercase">
                            Lifetime spend
                        </p>
                        <p className="text-xl font-semibold tabular-nums">
                            {formatMoney(Number(customer.lifetime_spend))}
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="mb-3 text-sm font-semibold">Details</div>
                    <dl className="grid gap-2 text-sm sm:grid-cols-2">
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Phone</dt>
                            <dd className="text-right font-medium">{customer.phone ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Email</dt>
                            <dd className="text-right font-medium">{customer.email ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Address</dt>
                            <dd className="text-right font-medium">{customer.address ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between gap-6">
                            <dt className="text-muted-foreground">Number</dt>
                            <dd className="text-right font-mono">{customer.customer_no}</dd>
                        </div>
                    </dl>
                </div>

                <div className="rounded-xl border">
                    <div className="border-b px-4 py-3 text-sm font-semibold">
                        Account ledger
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Type</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead className="text-right">Points</TableHead>
                                <TableHead className="text-right">Credit bal.</TableHead>
                                <TableHead className="text-right">Deposit bal.</TableHead>
                                <TableHead>Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transactions.map((t) => (
                                <TableRow key={t.id}>
                                    <TableCell>
                                        <Badge variant="secondary">{t.type}</Badge>
                                    </TableCell>
                                    <TableCell className="max-w-sm">
                                        <div className="text-sm">{t.description ?? '—'}</div>
                                        {t.creator && (
                                            <div className="text-muted-foreground text-xs">
                                                {t.creator.name}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(t.amount))}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {t.points_change
                                            ? `${t.points_change > 0 ? '+' : ''}${t.points_change}`
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {t.running_credit !== null
                                            ? formatMoney(Number(t.running_credit))
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {t.running_deposit !== null
                                            ? formatMoney(Number(t.running_deposit))
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(t.created_at)}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {transactions.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No account activity yet.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

function ActionDialog({
    open,
    onOpenChange,
    title,
    description,
    children,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button variant="outline">{title}</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

CustomersShow.layout = {
    breadcrumbs: [{ title: 'Customers', href: customerRoutes.index().url }],
};