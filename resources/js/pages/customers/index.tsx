import { Form, Head, Link, router } from '@inertiajs/react';
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
import { formatNumber } from '@/lib/format';

type Customer = {
    id: number;
    customer_no: string;
    name: string;
    phone: string | null;
    email: string | null;
    customer_type: string;
    is_active: boolean;
    sales_count: number;
    layaways_count: number;
    loyalty_tier: string;
    loyalty_points: number;
    deposit_balance: number;
    credit_balance: number;
    credit_limit: number;
};

type Props = {
    customers: {
        data: Customer[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { search?: string; type?: string };
};

const typeVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    walk_in: 'outline',
    member: 'default',
    credit: 'secondary',
};

export default function CustomersIndex({
    customers: customerList,
    filters,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [edit, setEdit] = useState<Customer | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const applyWith = (params: Record<string, string>) => {
        router.get(customerRoutes.index().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Customers" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Customers"
                        description="Customers known to the terminal — walk-ins, members and credit accounts."
                    />
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>Add customer</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Add customer</DialogTitle>
                                <DialogDescription>
                                    Record a new customer.
                                </DialogDescription>
                            </DialogHeader>
                            <CustomerForm onDone={() => setCreateOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            className="w-64"
                            placeholder="Name, phone or customer no…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) =>
                                e.key === 'Enter' && applyWith({ search })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="type-filter">Type</Label>
                        <select
                            id="type-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.type ?? ''}
                            onChange={(e) =>
                                applyWith({
                                    type:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All types</option>
                            <option value="walk-in">Walk-in</option>
                            <option value="member">Member</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() =>
                            applyWith({ search, type: filters.type ?? '' })
                        }
                    >
                        Apply
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Customer</TableHead>
                                <TableHead>No.</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Sales
                                </TableHead>
                                <TableHead className="text-right">
                                    Points
                                </TableHead>
                                <TableHead className="text-right">
                                    Deposit
                                </TableHead>
                                <TableHead className="text-right">
                                    Credit
                                </TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customerList.data.map((c) => (
                                <TableRow key={c.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {c.name}
                                        </div>
                                        {c.loyalty_tier !== 'bronze' && (
                                            <div className="text-muted-foreground uppercase text-xs">
                                                {c.loyalty_tier}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {c.customer_no}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {c.phone && <div>{c.phone}</div>}
                                        {c.email && (
                                            <div className="text-muted-foreground">
                                                {c.email}
                                            </div>
                                        )}
                                        {!c.phone && !c.email && '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                typeVariant[c.customer_type] ??
                                                'outline'
                                            }
                                        >
                                            {c.customer_type}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {c.is_active ? (
                                            <Badge variant="outline">
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge variant="destructive">
                                                Inactive
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(c.sales_count)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(c.loyalty_points)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(c.deposit_balance)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(c.credit_balance)}
                                        {c.credit_limit > 0 && (
                                            <div className="text-muted-foreground text-xs">
                                                / {formatNumber(c.credit_limit)}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link
                                                    href={customerRoutes.show.url({ customer: c.id })}
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setEdit(c)}
                                            >
                                                Edit
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {customerList.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={11}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No customers found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {customerList.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {customerList.current_page} of{' '}
                            {customerList.last_page} · {customerList.total}{' '}
                            total
                        </span>
                        <div className="flex gap-2">
                            {customerList.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={customerList.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {customerList.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={customerList.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={edit !== null}
                onOpenChange={(o) => !o && setEdit(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit customer</DialogTitle>
                        <DialogDescription>
                            Update customer information.
                        </DialogDescription>
                    </DialogHeader>
                    {edit && (
                        <CustomerForm
                            customer={edit}
                            onDone={() => setEdit(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function CustomerForm({
    customer,
    onDone,
}: {
    customer?: Customer;
    onDone: () => void;
}) {
    const controller = customer
        ? CustomerController.update.form({ customer: customer.id })
        : CustomerController.store.form();

    return (
        <Form
            {...controller}
            options={{ preserveScroll: true }}
            className="space-y-4"
            onSubmit={onDone}
        >
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={customer?.name}
                            required
                        />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                defaultValue={customer?.phone ?? ''}
                            />
                            <InputError message={errors?.phone} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                defaultValue={customer?.email ?? ''}
                            />
                            <InputError message={errors?.email} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="customer_type">Customer type</Label>
                        <select
                            id="customer_type"
                            name="customer_type"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={customer?.customer_type ?? 'walk-in'}
                        >
                            <option value="walk-in">Walk-in</option>
                            <option value="member">Member</option>
                            <option value="credit">Credit</option>
                        </select>
                        <InputError message={errors?.customer_type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="credit_limit">Credit limit</Label>
                        <Input
                            id="credit_limit"
                            name="credit_limit"
                            type="number"
                            min="0"
                            step="0.01"
                            defaultValue={customer?.credit_limit ?? 0}
                        />
                        <InputError message={errors?.credit_limit} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="address">Address</Label>
                        <Input id="address" name="address" />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            className="border-input min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm"
                        />
                    </div>
                    {customer && (
                        <Label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="is_active"
                                defaultChecked={customer.is_active}
                                className="h-4 w-4"
                            />
                            Active
                        </Label>
                    )}
                    <Button type="submit">
                        {customer ? 'Save changes' : 'Create customer'}
                    </Button>
                </>
            )}
        </Form>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Customers', href: customerRoutes.index().url }],
};
