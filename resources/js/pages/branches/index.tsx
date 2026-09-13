import { Form, Head, router } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import BranchController from '@/actions/App/Http/Controllers/BranchController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import { index } from '@/routes/branches';
import { formatNumber } from '@/lib/format';

type BranchRow = {
    id: number;
    name: string;
    code: string;
    type: 'godown' | 'store';
    address: string | null;
    phone: string | null;
    email: string | null;
    currency: string;
    is_active: boolean;
    users_count: number;
    parent: { id: number; name: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Props = {
    branches: Paginated<BranchRow>;
    godowns: { id: number; name: string }[];
    filters: { search?: string };
};

export default function BranchesIndex({ branches, godowns, filters }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editBranch, setEditBranch] = useState<BranchRow | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const applySearch = () => {
        router.get(index().url, { search: search || undefined }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Branches" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Branches"
                        description="Manage multi-branch locations and settings."
                    />
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>Create branch</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Create branch</DialogTitle>
                                <DialogDescription>
                                    Add a godown (warehouse) or a store to the system.
                                </DialogDescription>
                            </DialogHeader>
                            <BranchForm godowns={godowns} onDone={() => setCreateOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="branch-search">Search</Label>
                        <Input
                            id="branch-search"
                            className="w-64"
                            placeholder="Branch name…"
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
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Supplier (Godown)</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Currency</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Staff</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {branches.data.map((branch) => (
                                <TableRow key={branch.id}>
                                    <TableCell>
                                        <div className="font-medium">{branch.name}</div>
                                        <div className="text-muted-foreground max-w-[200px] truncate text-xs">
                                            {branch.address ?? '—'}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={branch.type === 'godown' ? 'default' : 'outline'}>
                                            {branch.type}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">{branch.code}</Badge>
                                    </TableCell>
                                    <TableCell className="text-sm">{branch.parent?.name ?? '—'}</TableCell>
                                    <TableCell className="text-sm">
                                        {branch.phone && branch.email
                                            ? `${branch.phone} · ${branch.email}`
                                            : (branch.phone ?? branch.email ?? '—')}
                                    </TableCell>
                                    <TableCell>{branch.currency}</TableCell>
                                    <TableCell>
                                        {branch.is_active ? (
                                            <Badge variant="outline">Active</Badge>
                                        ) : (
                                            <Badge variant="destructive">Inactive</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>{formatNumber(branch.users_count)}</TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setEditBranch(branch)}
                                        >
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {branches.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={9}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No branches found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog open={editBranch !== null} onOpenChange={(o) => !o && setEditBranch(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit: {editBranch?.name}</DialogTitle>
                        <DialogDescription>Update branch information.</DialogDescription>
                    </DialogHeader>
                    {editBranch && (
                        <BranchForm
                            godowns={godowns}
                            branch={editBranch}
                            onDone={() => setEditBranch(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function BranchForm({ branch, godowns, onDone }: { branch?: BranchRow; godowns: { id: number; name: string }[]; onDone: () => void }) {
    const controller = branch
        ? BranchController.update.form({ branch: branch.id })
        : BranchController.store.form();

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={() => onDone()}>
            {({ errors }) => (
                <>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Branch name</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={branch?.name}
                                placeholder="e.g. Dar es Salaam Central"
                                required
                            />
                            <InputError message={errors?.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                defaultValue={branch?.code}
                                placeholder="e.g. DSM01"
                                required
                            />
                            <InputError message={errors?.code} />
                        </div>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="type">Location type</Label>
                            <select
                                id="type"
                                name="type"
                                defaultValue={branch?.type ?? 'store'}
                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            >
                                <option value="store">Store / Shop</option>
                                <option value="godown">Godown (Warehouse)</option>
                            </select>
                            <InputError message={errors?.type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="parent_id">Supplied by (Godown)</Label>
                            <select
                                id="parent_id"
                                name="parent_id"
                                defaultValue={branch?.parent?.id ? String(branch.parent.id) : ''}
                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            >
                                <option value="">— No supplier —</option>
                                {godowns
                                    .filter((g) => g.id !== branch?.id)
                                    .map((g) => (
                                        <option key={g.id} value={String(g.id)}>
                                            {g.name}
                                        </option>
                                    ))}
                            </select>
                            <InputError message={errors?.parent_id} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="address">Address</Label>
                        <Input
                            id="address"
                            name="address"
                            defaultValue={branch?.address ?? ''}
                            placeholder="Full address"
                        />
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                defaultValue={branch?.phone ?? ''}
                                placeholder="+255 7xx xxx xxx"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                defaultValue={branch?.email ?? ''}
                                placeholder="branch@example.com"
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="currency">Currency</Label>
                        <Input
                            id="currency"
                            name="currency"
                            defaultValue={branch?.currency ?? 'TZS'}
                            placeholder="e.g. TZS"
                            required
                        />
                        <InputError message={errors?.currency} />
                    </div>
                    <DialogFooter>
                        <Button type="submit">{branch ? 'Save changes' : 'Create branch'}</Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

BranchesIndex.layout = {
    breadcrumbs: [{ title: 'Branches', href: index().url }],
};