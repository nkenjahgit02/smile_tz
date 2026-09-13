import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import orders from '@/routes/orders';

type OrderItem = {
    id: number;
    product_name: string | null;
    qty_requested: number;
    qty_approved: number | null;
    qty_dispatched: number | null;
    qty_received: number | null;
};

type OrderRow = {
    id: number;
    order_number: string;
    status: string;
    priority: 'normal' | 'urgent';
    required_date: string | null;
    items: OrderItem[];
    branch: { id: number; name: string } | null;
    godown: { id: number; name: string } | null;
};

type Props = {
    orders: OrderRow[];
    pipeline: Record<string, number>;
    filters: { status?: string; priority?: string; mine?: boolean };
    godowns: { id: number; name: string; code: string }[];
};

const STAGE_LABELS: Record<string, string> = {
    pending_approval: 'Pending Approval',
    picking: 'Picking',
    ready_for_dispatch: 'Ready for Dispatch',
    dispatched: 'Dispatched / In Transit',
    discrepancy_review: 'Discrepancy Review',
};

const statusVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    draft: 'outline',
    submitted: 'secondary',
    pending_approval: 'secondary',
    approved: 'default',
    partially_approved: 'default',
    rejected: 'destructive',
    pending_fulfillment: 'secondary',
    picking: 'secondary',
    ready_for_dispatch: 'default',
    dispatched: 'default',
    partially_received: 'secondary',
    received: 'default',
    discrepancy_review: 'destructive',
    closed: 'default',
    cancelled: 'destructive',
};

const ACTIVE_STAGES = Object.keys(STAGE_LABELS);

export default function OrdersIndex({
    orders: rows,
    pipeline,
    filters,
    godowns,
}: Props) {
    const [mine, setMine] = useState(filters.mine ?? false);

    const applyWith = (params: Record<string, string | number | boolean>) => {
        router.get(orders.index().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Branch Orders" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Branch Orders"
                        description="Request stock from the Godown, track picking and dispatch, and confirm receipt."
                    />
                    <Button asChild>
                        <Link href={orders.create().url}>New order</Link>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {ACTIVE_STAGES.map((stage) => {
                        const count = pipeline[stage] ?? 0;
                        const active = filters.status === stage;
                        return (
                            <Link
                                key={stage}
                                href={orders.index().url}
                                onClick={(e) => {
                                    e.preventDefault();
                                    applyWith({ status: active ? '' : stage });
                                }}
                                className={cn(
                                    'flex flex-col gap-1 rounded-xl border p-3 transition-colors',
                                    active
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted/50',
                                )}
                            >
                                <span className="text-sm font-medium">
                                    {STAGE_LABELS[stage]}
                                </span>
                                <span
                                    className={cn(
                                        'text-2xl font-semibold',
                                        count === 0 && 'text-muted-foreground',
                                    )}
                                >
                                    {count}
                                </span>
                            </Link>
                        );
                    })}
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="status-filter">Status</Label>
                        <select
                            id="status-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.status ?? ''}
                            onChange={(e) =>
                                applyWith({
                                    status:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All statuses</option>
                            <option value="pending_approval">
                                Pending Approval
                            </option>
                            <option value="picking">Picking</option>
                            <option value="ready_for_dispatch">
                                Ready for Dispatch
                            </option>
                            <option value="dispatched">
                                Dispatched / In Transit
                            </option>
                            <option value="discrepancy_review">
                                Discrepancy Review
                            </option>
                            <option value="closed">Closed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="priority-filter">Priority</Label>
                        <select
                            id="priority-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.priority ?? ''}
                            onChange={(e) =>
                                applyWith({
                                    priority:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All priorities</option>
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <label className="flex h-9 items-center gap-2 text-sm">
                        <Input
                            type="checkbox"
                            checked={mine}
                            onChange={(e) =>
                                applyWith({ mine: e.target.checked })
                            }
                        />
                        Only my orders
                    </label>
                </div>

                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground text-left text-xs">
                                <th className="px-3 py-2">Order</th>
                                <th className="px-3 py-2">Branch</th>
                                <th className="px-3 py-2">Godown</th>
                                <th className="px-3 py-2">Lines</th>
                                <th className="px-3 py-2">Priority</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((o) => (
                                <tr key={o.id} className="border-t">
                                    <td className="px-3 py-2 font-mono text-xs font-medium">
                                        {o.order_number}
                                    </td>
                                    <td className="px-3 py-2">
                                        {o.branch?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {o.godown?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline">
                                            {o.items.length} lines
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                o.priority === 'urgent'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {o.priority}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                statusVariant[o.status] ??
                                                'outline'
                                            }
                                        >
                                            {o.status}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={orders.show.url({
                                                    order: o.id,
                                                })}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No branch orders found.{' '}
                                        {godowns.length === 0 &&
                                            'You are not assigned to a Godown or branch that fulfills orders.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Orders', href: orders.index().url }],
};
