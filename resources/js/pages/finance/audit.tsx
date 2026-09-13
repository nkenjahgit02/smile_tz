import { Head, Link, router } from '@inertiajs/react';
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
import finance from '@/routes/finance';
import { formatDateTime } from '@/lib/format';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type AuditRow = {
    id: number;
    description: string;
    event: string | null;
    user: string;
    branch_id: number | null;
    created_at: string;
};

type Props = {
    activities: Paginated<AuditRow>;
    events: string[];
    filters: { from?: string; to?: string; event?: string };
};

export default function AuditIndex({ activities, events, filters }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(finance.audit().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Audit Trail" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Audit Trail"
                    description="Financial events only — sales, refunds, voids, cash movements and travel records."
                />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            className="h-9 w-40"
                            defaultValue={filters.from ?? ''}
                            onChange={(e) => applyWith({ from: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            className="h-9 w-40"
                            defaultValue={filters.to ?? ''}
                            onChange={(e) => applyWith({ to: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="event">Event</Label>
                        <select
                            id="event"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.event ?? ''}
                            onChange={(e) =>
                                applyWith({
                                    event: e.target.value === 'all' ? '' : e.target.value,
                                })
                            }
                        >
                            <option value="all">All events</option>
                            {events.map((event) => (
                                <option key={event} value={event}>
                                    {event}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Event</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>User</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>At</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {activities.data.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell>
                                        <Badge variant="outline">{a.event ?? '—'}</Badge>
                                    </TableCell>
                                    <TableCell className="max-w-lg truncate">
                                        {a.description}
                                    </TableCell>
                                    <TableCell>{a.user}</TableCell>
                                    <TableCell>{a.branch_id ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(a.created_at)}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {activities.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No financial activity found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {activities.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {activities.current_page} of {activities.last_page} ·{' '}
                            {activities.total} total
                        </span>
                        <div className="flex gap-2">
                            {activities.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={activities.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {activities.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={activities.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit Trail', href: finance.audit().url }],
};