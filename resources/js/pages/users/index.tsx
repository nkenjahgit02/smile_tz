import { Form, Head, router } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SelectField } from '@/components/select-field';
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
import { index } from '@/routes/users';
import { formatDateTime } from '@/lib/format';

type Branch = { id: number; name: string };
type UserRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    branch_id: number | null;
    is_active: boolean;
    created_at: string;
    branch: Branch | null;
    roles: { id: number; name: string }[];
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
    users: Paginated<UserRow>;
    roles: Record<string, number>;
    branches: Branch[];
    filters: { search?: string; role?: string; branch_id?: string };
};

export default function UsersIndex({ users, roles, branches, filters }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editUser, setEditUser] = useState<UserRow | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = () => {
        router.get(index().url, { search: search || undefined }, { preserveState: true, preserveScroll: true });
    };

    const roleOptions = Object.entries(roles).map(([label, id]) => ({
        label,
        value: String(id),
    }));
    const branchOptions = branches.map((b) => ({ label: b.name, value: String(b.id) }));

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Manage staff accounts, roles and branch assignments."
                    />

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>Create user</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Create user</DialogTitle>
                                <DialogDescription>
                                    Create a new staff account with a role and branch.
                                </DialogDescription>
                            </DialogHeader>
                            <UserForm
                                roleOptions={roleOptions}
                                branchOptions={branchOptions}
                                onDone={() => setCreateOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            className="w-64"
                            placeholder="Name, email or phone…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="role-filter">Role</Label>
                        <select
                            id="role-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.role ?? ''}
                            onChange={(e) =>
                                applyWith({ role: e.target.value === 'all' ? '' : e.target.value })
                            }
                        >
                            <option value="all">All roles</option>
                            {Object.entries(roles).map(([name, id]) => (
                                <option key={id} value={name}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="branch-filter">Branch</Label>
                        <select
                            id="branch-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.branch_id ?? ''}
                            onChange={(e) =>
                                applyWith({ branch_id: e.target.value === 'all' ? '' : e.target.value })
                            }
                        >
                            <option value="all">All branches</option>
                            {branches.map((b) => (
                                <option key={b.id} value={String(b.id)}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button variant="outline" onClick={applyFilters}>
                        Apply
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <div className="font-medium">{user.name}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {user.email}
                                            {user.phone ? ` · ${user.phone}` : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {user.roles[0]?.name ?? 'No role'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{user.branch?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        {user.is_active ? (
                                            <Badge variant="outline">Active</Badge>
                                        ) : (
                                            <Badge variant="destructive">Inactive</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(user.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setEditUser(user)}
                                        >
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {users.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No users found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar users={users} />
            </div>

            <Dialog open={editUser !== null} onOpenChange={(o) => !o && setEditUser(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit user</DialogTitle>
                        <DialogDescription>
                            Update account details, role or branch.
                        </DialogDescription>
                    </DialogHeader>
                    {editUser && (
                        <UserForm
                            user={editUser}
                            roleOptions={roleOptions}
                            branchOptions={branchOptions}
                            onDone={() => setEditUser(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function applyWith(params: Record<string, string>) {
    router.get(index().url, params, { preserveState: true, preserveScroll: true });
}

function UserForm({
    user,
    roleOptions,
    branchOptions,
    onDone,
}: {
    user?: UserRow;
    roleOptions: { label: string; value: string }[];
    branchOptions: { label: string; value: string }[];
    onDone: () => void;
}) {
    const controller = user
        ? UserController.update.form({ user: user.id })
        : UserController.store.form();

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={() => onDone()}>
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Full name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={user?.name}
                            placeholder="e.g. Aisha Mohamed"
                            required
                        />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            defaultValue={user?.email}
                            placeholder="name@example.com"
                            required
                        />
                        <InputError message={errors?.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            defaultValue={user?.phone ?? ''}
                            placeholder="+255 7xx xxx xxx"
                        />
                        <InputError message={errors?.phone} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">
                            {user ? 'New password (leave blank to keep)' : 'Password'}
                        </Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            placeholder="••••••••"
                        />
                        <InputError message={errors?.password} />
                    </div>
                    <SelectField
                        name="role"
                        label="Role"
                        options={roleOptions}
                        placeholder="Select a role"
                        defaultValue={user ? String(user.roles[0]?.id ?? '') : ''}
                        error={errors?.role}
                    />
                    <SelectField
                        name="branch_id"
                        label="Branch"
                        options={branchOptions}
                        placeholder="Select a branch"
                        defaultValue={user?.branch_id ? String(user.branch_id) : ''}
                        error={errors?.branch_id}
                    />
                    <DialogFooter>
                        <Button type="submit">{user ? 'Save changes' : 'Create user'}</Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

function PaginationBar({ users }: { users: Paginated<UserRow> }) {
    if (users.last_page <= 1) return null;

    return (
        <div className="text-muted-foreground flex items-center justify-between text-sm">
            <span>
                Page {users.current_page} of {users.last_page} · {users.total} total
            </span>
            <div className="flex gap-2">
                {users.prev_page_url && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={users.prev_page_url}>Previous</Link>
                    </Button>
                )}
                {users.next_page_url && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={users.next_page_url}>Next</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index().url }],
};