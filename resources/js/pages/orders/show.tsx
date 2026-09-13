import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import orders from '@/routes/orders';

type StockRow = {
    branch: string | null;
    type: string | null;
    product_id: number;
    quantity: number;
    reserved: number;
    in_transit: number;
    available: number;
};

type Item = {
    id: number;
    product_id: number;
    product_name: string | null;
    qty_requested: number;
    qty_approved: number | null;
    qty_picked: number | null;
    qty_dispatched: number | null;
    qty_received: number | null;
    qty_damaged: number | null;
    qty_shortage: number | null;
    unit_cost: number | null;
};

type HistoryRow = {
    id: number;
    from_status: string | null;
    to_status: string;
    user: string | null;
    reason: string | null;
    created_at: string | null;
};

type Order = {
    id: number;
    order_number: string;
    status: string;
    priority: string;
    required_date: string | null;
    notes: string | null;
    branch: { id: number; name: string } | null;
    godown: { id: number; name: string } | null;
    items: Item[];
    history: HistoryRow[];
    dispatched_at: string | null;
    received_at: string | null;
    requires_second_approval: boolean;
};

type Props = {
    order: Order;
    current_stock: StockRow[];
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

export default function OrdersShow({ order, current_stock }: Props) {
    const [submitKey] = useState(() => {
        const rand =
            typeof crypto !== 'undefined' && crypto.randomUUID
                ? crypto.randomUUID().slice(0, 12)
                : String(Date.now());
        return `od-${order.id}-${rand}`;
    });
    const [reason, setReason] = useState('');
    const [resolution, setResolution] = useState('');
    const [approvals, setApprovals] = useState<Record<number, string>>(
        Object.fromEntries(
            order.items.map((i) => [
                i.id,
                String(i.qty_approved ?? i.qty_requested),
            ]),
        ),
    );
    const [picked, setPicked] = useState<Record<number, string>>(
        Object.fromEntries(
            order.items.map((i) => [
                i.id,
                String(i.qty_approved ?? i.qty_requested),
            ]),
        ),
    );
    const [dispatched, setDispatched] = useState<Record<number, string>>(
        Object.fromEntries(
            order.items.map((i) => [
                i.id,
                String(i.qty_picked ?? i.qty_approved ?? i.qty_requested),
            ]),
        ),
    );
    const [receipt, setReceipt] = useState<
        Record<number, { received: string; damaged: string }>
    >(
        Object.fromEntries(
            order.items.map((i) => [
                i.id,
                { received: String(i.qty_dispatched ?? 0), damaged: '0' },
            ]),
        ),
    );
    const [dispatchNoteNo, setDispatchNoteNo] = useState('');

    const stockFor = (productId: number): StockRow[] =>
        current_stock.filter((s) => s.product_id === productId);
    const totalRequested = order.items.reduce(
        (sum, i) => sum + i.qty_requested,
        0,
    );

    const canSubmit = order.status === 'draft';
    const canApprove = order.status === 'pending_approval';
    const canPick = order.status === 'picking';
    const canDispatch = order.status === 'ready_for_dispatch';
    const canReceive = order.status === 'dispatched';
    const canResolve =
        order.status === 'discrepancy_review' ||
        order.status === 'partially_received';
    const canCancel = [
        'draft',
        'submitted',
        'pending_approval',
        'pending_fulfillment',
    ].includes(order.status);

    return (
        <>
            <Head title={`Order ${order.order_number}`} />
            <div className="mx-auto flex max-w-5xl flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Heading
                            title={order.order_number}
                            description={`${order.branch?.name ?? '—'} ordering from ${order.godown?.name ?? '—'}`}
                        />
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge
                                variant={
                                    statusVariant[order.status] ?? 'outline'
                                }
                            >
                                {order.status}
                            </Badge>
                            <Badge
                                variant={
                                    order.priority === 'urgent'
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {order.priority}
                            </Badge>
                            {order.requires_second_approval && (
                                <Badge variant="secondary">
                                    2 approvals required
                                </Badge>
                            )}
                            {order.required_date && (
                                <Badge variant="outline">
                                    Required by{' '}
                                    {formatDate(order.required_date)}
                                </Badge>
                            )}
                        </div>
                    </div>
                    {canSubmit && (
                        <Form
                            {...OrderController.submit.form({
                                order: order.id,
                            })}
                            className="flex items-end gap-2"
                        >
                            <Input
                                type="hidden"
                                name="idempotency_key"
                                value={submitKey}
                            />
                            <Button type="submit">Submit for approval</Button>
                        </Form>
                    )}
                </div>

                {order.notes && (
                    <div className="text-muted-foreground rounded-lg border px-3 py-2 text-sm">
                        <strong>Notes:</strong> {order.notes}
                    </div>
                )}

                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground text-left text-xs">
                                <th className="px-3 py-2">Product</th>
                                <th className="px-3 py-2 text-right">
                                    Requested
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Approved
                                </th>
                                <th className="px-3 py-2 text-right">Picked</th>
                                <th className="px-3 py-2 text-right">
                                    Dispatched
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Received
                                </th>
                                <th className="px-3 py-2">Stock (available)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {order.items.map((i) => (
                                <tr key={i.id} className="border-t">
                                    <td className="px-3 py-2">
                                        {i.product_name ??
                                            `Product #${i.product_id}`}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {i.qty_requested}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {i.qty_approved === null
                                            ? '—'
                                            : i.qty_approved}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {i.qty_picked === null
                                            ? '—'
                                            : i.qty_picked}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {i.qty_dispatched === null
                                            ? '—'
                                            : i.qty_dispatched}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {i.qty_received === null
                                            ? '—'
                                            : `${i.qty_received}${i.qty_shortage && i.qty_shortage > 0 ? ` (short ${i.qty_shortage})` : ''}${i.qty_damaged ? ` (damaged ${i.qty_damaged})` : ''}`}
                                    </td>
                                    <td className="px-3 py-2">
                                        {stockFor(i.product_id).length === 0 ? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        ) : (
                                            stockFor(i.product_id).map((s) => (
                                                <span
                                                    key={s.branch}
                                                    className={cn(
                                                        'mr-2 inline-block font-mono text-xs',
                                                        s.available <= 0 &&
                                                            'text-red-600',
                                                    )}
                                                    title={`${s.branch} (${s.type}) · qty ${s.quantity} · reserved ${s.reserved} · in-transit ${s.in_transit}`}
                                                >
                                                    {s.branch}: {s.available}
                                                </span>
                                            ))
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t text-sm">
                                <td className="px-3 py-2 font-medium">
                                    Total requested
                                </td>
                                <td className="px-3 py-2 text-right font-medium">
                                    {totalRequested}
                                </td>
                                <td colSpan={5} />
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {canApprove && (
                    <Form
                        {...OrderController.approve.form({ order: order.id })}
                        className="space-y-3 rounded-xl border p-4"
                        options={{ preserveScroll: true }}
                    >
                        <h3 className="text-sm font-semibold">Approve order</h3>
                        <p className="text-muted-foreground text-xs">
                            Adjust approved quantities per line, or leave as
                            requested. Orders above the configured threshold
                            require a second approval by a Godown manager.
                        </p>
                        <div className="space-y-2">
                            {order.items.map((i, idx) => {
                                const base = `items[${idx}]`;
                                return (
                                    <div
                                        key={i.id}
                                        className="grid grid-cols-2 items-center gap-2"
                                    >
                                        <span className="text-sm">
                                            {i.product_name}
                                        </span>
                                        <div className="flex items-center gap-1">
                                            <Input
                                                type="hidden"
                                                name={`${base}[item_id]`}
                                                value={String(i.id)}
                                            />
                                            <Input
                                                type="number"
                                                name={`${base}[qty]`}
                                                step="0.001"
                                                min="0"
                                                max={i.qty_requested}
                                                value={approvals[i.id]}
                                                onChange={(e) =>
                                                    setApprovals((p) => ({
                                                        ...p,
                                                        [i.id]: e.target.value,
                                                    }))
                                                }
                                            />
                                            <span className="text-muted-foreground text-xs">
                                                / {i.qty_requested}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="grid gap-2">
                            <Label>Approval note</Label>
                            <Textarea
                                name="reason"
                                placeholder="Optional"
                                rows={2}
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit">Approve</Button>
                        </div>
                    </Form>
                )}

                {canApprove && (
                    <div className="grid gap-3 rounded-xl border p-4">
                        <h3 className="text-sm font-semibold">
                            Reject or return
                        </h3>
                        <Textarea
                            className="min-h-16"
                            placeholder="Reason (required)"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        />
                        <div className="flex gap-2">
                            <Form
                                {...OrderController.reject.form({
                                    order: order.id,
                                })}
                            >
                                <Input
                                    type="hidden"
                                    name="reason"
                                    value={reason}
                                />
                                <Button type="submit" variant="destructive">
                                    Reject
                                </Button>
                            </Form>
                            <Form
                                {...OrderController.returnForChanges.form({
                                    order: order.id,
                                })}
                            >
                                <Input
                                    type="hidden"
                                    name="reason"
                                    value={reason}
                                />
                                <Button type="submit" variant="outline">
                                    Return for changes
                                </Button>
                            </Form>
                        </div>
                    </div>
                )}

                {canPick && (
                    <Form
                        {...OrderController.completePicking.form({
                            order: order.id,
                        })}
                        className="space-y-3 rounded-xl border p-4"
                        options={{ preserveScroll: true }}
                    >
                        <h3 className="text-sm font-semibold">
                            Complete picking
                        </h3>
                        <p className="text-muted-foreground text-xs">
                            Record the quantity actually available per line.
                            Shortages will be reflected in the dispatch.
                        </p>
                        {order.items.map((i, idx) => {
                            const base = `items[${idx}]`;
                            const max = i.qty_approved ?? i.qty_requested;
                            return (
                                <div
                                    key={i.id}
                                    className="grid grid-cols-2 items-center gap-2"
                                >
                                    <span className="text-sm">
                                        {i.product_name}
                                    </span>
                                    <div className="flex items-center gap-1">
                                        <Input
                                            type="hidden"
                                            name={`${base}[item_id]`}
                                            value={String(i.id)}
                                        />
                                        <Input
                                            type="number"
                                            name={`${base}[qty]`}
                                            step="0.001"
                                            min="0"
                                            max={max}
                                            value={picked[i.id]}
                                            onChange={(e) =>
                                                setPicked((p) => ({
                                                    ...p,
                                                    [i.id]: e.target.value,
                                                }))
                                            }
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            of {max}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                        <Button type="submit">Complete picking</Button>
                    </Form>
                )}

                {canDispatch && (
                    <Form
                        {...OrderController.dispatch.form({ order: order.id })}
                        className="space-y-3 rounded-xl border p-4"
                        options={{ preserveScroll: true }}
                    >
                        <h3 className="text-sm font-semibold">
                            Dispatch order
                        </h3>
                        <p className="text-muted-foreground text-xs">
                            Dispatch moves stock out of the Godown and marks it
                            in-transit to the branch. Verify available stock is
                            sufficient per line.
                        </p>
                        {order.items.map((i, idx) => {
                            const base = `items[${idx}]`;
                            const max =
                                i.qty_picked ??
                                i.qty_approved ??
                                i.qty_requested;
                            return (
                                <div
                                    key={i.id}
                                    className="grid grid-cols-2 items-center gap-2"
                                >
                                    <span className="text-sm">
                                        {i.product_name}
                                    </span>
                                    <div className="flex items-center gap-1">
                                        <Input
                                            type="hidden"
                                            name={`${base}[item_id]`}
                                            value={String(i.id)}
                                        />
                                        <Input
                                            type="number"
                                            name={`${base}[qty]`}
                                            step="0.001"
                                            min="0"
                                            max={max}
                                            value={dispatched[i.id]}
                                            onChange={(e) =>
                                                setDispatched((p) => ({
                                                    ...p,
                                                    [i.id]: e.target.value,
                                                }))
                                            }
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            of {max}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                        <div className="grid gap-2">
                            <Label htmlFor="dispatch_note_no">
                                Dispatch note no.
                            </Label>
                            <Input
                                id="dispatch_note_no"
                                name="dispatch_note_no"
                                placeholder="Auto-generated if left blank"
                                value={dispatchNoteNo}
                                onChange={(e) =>
                                    setDispatchNoteNo(e.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Button type="submit">Dispatch</Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={orders.dispatchNote.url({
                                        order: order.id,
                                    })}
                                    download
                                >
                                    Preview dispatch note
                                </Link>
                            </Button>
                        </div>
                    </Form>
                )}

                {canReceive && (
                    <Form
                        {...OrderController.receive.form({ order: order.id })}
                        className="space-y-3 rounded-xl border p-4"
                        options={{ preserveScroll: true }}
                    >
                        <h3 className="text-sm font-semibold">
                            Confirm receipt
                        </h3>
                        <p className="text-muted-foreground text-xs">
                            Enter what actually arrived. Shortage or damage
                            routes the order to discrepancy review.
                        </p>
                        {order.items.map((i, idx) => {
                            const base = `items[${idx}]`;
                            const line = receipt[i.id];
                            return (
                                <div
                                    key={i.id}
                                    className="grid grid-cols-12 items-center gap-2"
                                >
                                    <span className="col-span-6 text-sm">
                                        {i.product_name}
                                    </span>
                                    <Input
                                        type="hidden"
                                        name={`${base}[item_id]`}
                                        value={String(i.id)}
                                    />
                                    <div className="col-span-3 grid grid-cols-2 items-center gap-2">
                                        <Input
                                            type="number"
                                            name={`${base}[received]`}
                                            step="0.001"
                                            min="0"
                                            value={line.received}
                                            onChange={(e) =>
                                                setReceipt((p) => ({
                                                    ...p,
                                                    [i.id]: {
                                                        ...line,
                                                        received:
                                                            e.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            received
                                        </span>
                                    </div>
                                    <div className="col-span-3 grid grid-cols-2 items-center gap-2">
                                        <Input
                                            type="number"
                                            name={`${base}[damaged]`}
                                            step="0.001"
                                            min="0"
                                            value={line.damaged}
                                            onChange={(e) =>
                                                setReceipt((p) => ({
                                                    ...p,
                                                    [i.id]: {
                                                        ...line,
                                                        damaged: e.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            damaged
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                        <Button type="submit">Confirm receipt</Button>
                    </Form>
                )}

                {canResolve && (
                    <Form
                        {...OrderController.resolve.form({ order: order.id })}
                        className="space-y-3 rounded-xl border p-4"
                    >
                        <h3 className="text-sm font-semibold">
                            Resolve discrepancy
                        </h3>
                        <p className="text-muted-foreground text-xs">
                            Both the branch manager and godown manager agree on
                            the resolution (replacement, credit, write-off,
                            etc.). The order then closes.
                        </p>
                        <Textarea
                            name="resolution"
                            placeholder="Resolution notes"
                            value={resolution}
                            onChange={(e) => setResolution(e.target.value)}
                        />
                        <Button type="submit">Resolve and close</Button>
                    </Form>
                )}

                {canCancel && (
                    <Form
                        {...OrderController.cancel.form({ order: order.id })}
                        className="rounded-xl border p-4"
                    >
                        <Input
                            name="reason"
                            placeholder="Cancellation reason (required)"
                            required
                        />
                        <Button
                            type="submit"
                            variant="destructive"
                            className="mt-2"
                        >
                            Cancel order
                        </Button>
                    </Form>
                )}

                {order.history.length > 0 && (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 text-sm font-semibold">History</h3>
                        <ol className="space-y-3">
                            {order.history.map((h) => (
                                <li
                                    key={h.id}
                                    className="flex flex-col gap-0.5 border-l-2 pl-3 text-sm"
                                >
                                    <span className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            {h.from_status ?? '—'}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            →
                                        </span>
                                        <Badge
                                            variant={
                                                statusVariant[h.to_status] ??
                                                'outline'
                                            }
                                        >
                                            {h.to_status}
                                        </Badge>
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        {h.user ?? 'System'} ·{' '}
                                        {h.created_at
                                            ? formatDate(h.created_at)
                                            : ''}
                                    </span>
                                    {h.reason && (
                                        <span className="text-xs">
                                            {h.reason}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </div>
                )}
            </div>
        </>
    );
}

OrdersShow.layout = {
    breadcrumbs: [
        { title: 'Orders', href: orders.index().url },
        { title: (o: Props) => o.order.order_number, href: orders.show },
    ],
};
