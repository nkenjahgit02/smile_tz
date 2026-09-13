import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
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
import activity from '@/routes/activity';
import { formatDateTime } from '@/lib/format';

const index = activity.index;

type LogRow = {
    id: number;
    description: string;
    event: string | null;
    log_name: string | null;
    created_at: string;
    role: string | null;
    branch_id: number | null;
    causer: { id: number; name: string; email: string } | null;
    properties: {
        email?: string;
        ip_address?: string;
        user_agent?: string;
        role?: string;
        why?: string;
    };
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
    logs: Paginated<LogRow>;
    users: { id: number; name: string }[];
    branches: { id: number; name: string }[];
    events: string[];
    roles: string[];
    filters: {
        user_id?: string;
        role?: string;
        event?: string;
        branch_id?: string;
        date_from?: string;
        date_to?: string;
        search?: string;
    };
};

export default function ActivityIndex({ logs, users, branches, events, roles, filters }: Props) {
    const { auth } = usePage().props as unknown as {
        auth: { user: { permissions: string[] } };
    };
    const canExport = auth.user.permissions.includes('activity.export');
    const [search, setSearch] = useState(filters.search ?? '');

    const applyWith = (params: Record<string, string>) => {
        router.get(index().url, params, { preserveState: true, preserveScroll: true });
    };

    const reset = () => router.get(index().url, {}, { preserveState: true, preserveScroll: true });

    const exportRoute = activity.export;

    const exportLogs = (format: string) => {
        window.location.href = exportRoute.url({
            query: {
                format,
                search: filters.search,
                user_id: filters.user_id,
                role: filters.role,
                event: filters.event,
                branch_id: filters.branch_id,
                date_from: filters.date_from,
                date_to: filters.date_to,
            } as Record<string, string>,
        });
    };

    const eventStyles = (event: string | null) =>
        ({
            login: 'outline',
            logout: 'outline',
            failed_login: 'destructive',
            created: 'outline',
            updated: 'outline',
            deleted: 'destructive',
            refunded: 'destructive',
            'loss-sale': 'destructive',
            expense_created: 'outline',
            stock_adjusted: 'outline',
            sale_completed: 'outline',
        })[event ?? ''] ? 'outline' : 'outline';

    return (
        <>
            <Head title="Activity Log" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Activity Log"
                        description="Audit trail of who did what, when and from where."
                    />

                    {canExport && (
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => exportLogs('xlsx')}
                            >
                                Excel
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => exportLogs('csv')}
                            >
                                CSV
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => exportLogs('pdf')}
                            >
                                PDF
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            className="w-56"
                            placeholder="Search action…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyWith({ search })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-filter">User</Label>
                        <select
                            id="user-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.user_id ?? ''}
                            onChange={(e) =>
                                applyWith({ user_id: e.target.value === 'all' ? '' : e.target.value })
                            }
                        >
                            <option value="all">All users</option>
                            {users.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="event-filter">Event</Label>
                        <select
                            id="event-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.event ?? ''}
                            onChange={(e) =>
                                applyWith({ event: e.target.value === 'all' ? '' : e.target.value })
                            }
                        >
                            <option value="all">All events</option>
                            {events.map((ev) => (
                                <option key={ev} value={ev}>
                                    {ev}
                                </option>
                            ))}
                        </select>
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
                            {roles.map((r) => (
                                <option key={r} value={r}>
                                    {r}
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
                    <div className="grid gap-2">
                        <Label htmlFor="date_from">From</Label>
                        <Input
                            id="date_from"
                            type="date"
                            defaultValue={filters.date_from ?? ''}
                            onChange={(e) => applyWith({ date_from: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="date_to">To</Label>
                        <Input
                            id="date_to"
                            type="date"
                            defaultValue={filters.date_to ?? ''}
                            onChange={(e) => applyWith({ date_to: e.target.value })}
                        />
                    </div>
                    <Button variant="outline" onClick={reset}>
                        Reset
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Timestamp</TableHead>
                                <TableHead>User</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Event</TableHead>
                                <TableHead>Where</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="text-muted-foreground whitespace-nowrap">
                                        {formatDateTime(log.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        {log.causer ? (
                                            <>
                                                <div className="font-medium">{log.causer.name}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {log.causer.email}
                                                    {log.role ? ` · ${log.role}` : ''}
                                                </div>
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                System
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-sm">
                                        <div className="truncate">{log.description}</div>
                                        {log.properties?.why && (
                                            <div className="text-muted-foreground text-xs">
                                                {log.properties.why}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={eventStyles(log.event) as 'outline' | 'destructive'}
                                        >
                                            {log.event ?? '—'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-xs">
                                        <div>{log.branch_id ? `Branch #${log.branch_id}` : '—'}</div>
                                        {log.properties?.ip_address && (
                                            <div className="tabular-nums">
                                                {log.properties.ip_address}
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {logs.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No activity found for the selected filters.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {logs.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {logs.current_page} of {logs.last_page} · {logs.total} total
                        </span>
                        <div className="flex gap-2">
                            {logs.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={logs.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {logs.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={logs.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ActivityIndex.layout = {
    breadcrumbs: [{ title: 'Activity Log', href: index().url }],
};