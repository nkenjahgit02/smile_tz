import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import SupplierController from '@/actions/App/Http/Controllers/SupplierController';
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
import supplierRoutes from '@/routes/suppliers';
import { formatMoney } from '@/lib/format';

type SupplierRow = {
    id: number;
    name: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    opening_balance: string;
    is_active: boolean;
    notes: string | null;
};

type Props = {
    suppliers: {
        data: SupplierRow[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { search?: string };
};

export default function SuppliersIndex({ suppliers, filters }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [edit, setEdit] = useState<SupplierRow | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const applySearch = () => {
        router.get(supplierRoutes.index().url, { search: search || undefined }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Suppliers" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Suppliers"
                        description="Vendors you purchase stock from."
                    />
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>Add supplier</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Add supplier</DialogTitle>
                                <DialogDescription>Record a new vendor.</DialogDescription>
                            </DialogHeader>
                            <SupplierForm onDone={() => setCreateOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            className="w-64"
                            placeholder="Name, contact…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                        />
                    </div>
                    <Button variant="outline" onClick={applySearch}>
                        Apply
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Opening Balance</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {suppliers.data.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell>
                                        <div className="font-medium">{s.name}</div>
                                        <div className="text-muted-foreground text-xs">{s.address}</div>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {s.contact_person && <div>{s.contact_person}</div>}
                                        {s.phone && <div className="text-muted-foreground">{s.phone}</div>}
                                        {s.email && <div className="text-muted-foreground">{s.email}</div>}
                                        {!s.contact_person && !s.phone && !s.email && '—'}
                                    </TableCell>
                                    <TableCell>{formatMoney(Number(s.opening_balance))}</TableCell>
                                    <TableCell>
                                        <Badge variant={s.is_active ? 'outline' : 'secondary'}>
                                            {s.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="outline" size="sm" onClick={() => setEdit(s)}>
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {suppliers.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                        No suppliers found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {suppliers.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {suppliers.current_page} of {suppliers.last_page} · {suppliers.total} total
                        </span>
                        <div className="flex gap-2">
                            {suppliers.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={suppliers.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {suppliers.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={suppliers.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Dialog open={edit !== null} onOpenChange={(o) => !o && setEdit(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit supplier</DialogTitle>
                    </DialogHeader>
                    {edit && <SupplierForm supplier={edit} onDone={() => setEdit(null)} />}
                </DialogContent>
            </Dialog>
        </>
    );
}

function SupplierForm({ supplier, onDone }: { supplier?: SupplierRow; onDone: () => void }) {
    const controller = supplier
        ? SupplierController.update.form({ supplier: supplier.id })
        : SupplierController.store.form();

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={onDone}>
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Supplier name</Label>
                        <Input id="name" name="name" defaultValue={supplier?.name} required />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="contact_person">Contact person</Label>
                        <Input id="contact_person" name="contact_person" defaultValue={supplier?.contact_person ?? ''} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input id="phone" name="phone" defaultValue={supplier?.phone ?? ''} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" name="email" type="email" defaultValue={supplier?.email ?? ''} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="address">Address</Label>
                        <Input id="address" name="address" defaultValue={supplier?.address ?? ''} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="opening_balance">Opening balance</Label>
                        <Input
                            id="opening_balance"
                            name="opening_balance"
                            type="number"
                            step="0.01"
                            min="0"
                            defaultValue={supplier?.opening_balance ?? '0'}
                        />
                        <InputError message={errors?.opening_balance} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            className="border-input min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm"
                            defaultValue={supplier?.notes ?? ''}
                        />
                    </div>
                    {supplier && (
                        <Label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" defaultChecked={supplier.is_active} className="h-4 w-4" />
                            Active
                        </Label>
                    )}
                    <Button type="submit">{supplier ? 'Save changes' : 'Create supplier'}</Button>
                </>
            )}
        </Form>
    );
}

SuppliersIndex.layout = {
    breadcrumbs: [{ title: 'Suppliers', href: supplierRoutes.index().url }],
};