import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
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
import { index } from '@/routes/roles';

type RoleRow = {
    id: number;
    name: string;
    users_count: number;
    permissions: string[];
};

type Props = {
    roles: RoleRow[];
    permissionGroups: Record<string, string[]>;
};

export default function RolesIndex({ roles, permissionGroups }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editRole, setEditRole] = useState<RoleRow | null>(null);

    return (
        <>
            <Head title="Roles & Permissions" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Roles & Permissions"
                        description="Create roles and control what each role can access."
                    />
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>Create role</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Create role</DialogTitle>
                                <DialogDescription>
                                    Add a new role and assign permissions below.
                                </DialogDescription>
                            </DialogHeader>
                            <RoleForm
                                isEdit={false}
                                permissionGroups={permissionGroups}
                                onDone={() => setCreateOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Users</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell className="font-medium">
                                        {role.name}
                                        {['Owner', 'Admin', 'Finance', 'Seller'].includes(role.name) && (
                                            <Badge variant="secondary" className="ml-2 text-xs">
                                                System
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>{role.users_count}</TableCell>
                                    <TableCell className="text-muted-foreground max-w-xs truncate text-sm">
                                        {role.permissions.length} permissions
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="outline" size="sm" onClick={() => setEditRole(role)}>
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {roles.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-muted-foreground h-24 text-center">
                                        No roles found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog open={editRole !== null} onOpenChange={(o) => !o && setEditRole(null)}>
                <DialogContent className="max-h-[90vh] sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit: {editRole?.name}</DialogTitle>
                        <DialogDescription>
                            Toggle the permissions this role should have.
                        </DialogDescription>
                    </DialogHeader>
                    {editRole && (
                        <RoleForm
                            isEdit
                            role={editRole}
                            permissionGroups={permissionGroups}
                            onDone={() => setEditRole(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function RoleForm({
    isEdit,
    role,
    permissionGroups,
    onDone,
}: {
    isEdit: boolean;
    role?: RoleRow;
    permissionGroups: Record<string, string[]>;
    onDone: () => void;
}) {
    const controller = isEdit
        ? RoleController.update.form({ role: role!.id })
        : RoleController.store.form();

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={() => onDone()}>
            {({ errors }) => (
                <RoleFormFields
                    isEdit={isEdit}
                    permissionGroups={permissionGroups}
                    currentPermissions={role?.permissions ?? []}
                    errors={errors}
                />
            )}
        </Form>
    );
}

function RoleFormFields({
    isEdit,
    permissionGroups,
    currentPermissions,
    errors,
}: {
    isEdit: boolean;
    permissionGroups: Record<string, string[]>;
    currentPermissions: string[];
    errors?: Record<string, string> | null;
}) {
    const [selected, setSelected] = useState<string[]>(currentPermissions);

    const toggle = (perm: string) => {
        setSelected((prev) =>
            prev.includes(perm) ? prev.filter((p) => p !== perm) : [...prev, perm],
        );
    };

    return (
        <>
            {!isEdit && (
                <div className="grid gap-2">
                    <Label htmlFor="role-name">Role name</Label>
                    <Input
                        id="role-name"
                        name="name"
                        placeholder="e.g. Stock Manager"
                        required
                    />
                    <InputError message={errors?.name} />
                </div>
            )}

            <div className="space-y-4 overflow-y-auto pr-1" style={{ maxHeight: '50vh' }}>
                {Object.entries(permissionGroups).map(([group, permissions]) => (
                    <div key={group}>
                        <h4 className="mb-2 text-sm font-medium">{group}</h4>
                        <div className="grid grid-cols-1 gap-1 sm:grid-cols-2 md:grid-cols-3">
                            {permissions.map((perm) => (
                                <label
                                    key={perm}
                                    className="flex cursor-pointer items-center gap-2 rounded border px-3 py-1.5 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value={perm}
                                        checked={selected.includes(perm)}
                                        onChange={() => toggle(perm)}
                                        className="size-4 rounded border-gray-300"
                                    />
                                    <span className="text-muted-foreground truncate">
                                        {perm.replace('.', ' ')}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
            <InputError message={errors?.permissions} />
            <DialogFooter>
                <Button type="submit">{isEdit ? 'Save changes' : 'Create role'}</Button>
            </DialogFooter>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles & Permissions', href: index().url }],
};