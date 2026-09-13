import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StockTakeController from '@/actions/App/Http/Controllers/StockTakeController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import stockTakes from '@/routes/stock-takes';
import { formatDateTime } from '@/lib/format';

type Item = {
    id: number;
    system_quantity: string;
    counted_quantity: string;
    difference: string;
    reason: string | null;
    product: { id: number; name: string; sku: string } | null;
    variant: { id: number; name: string } | null;
};

type Take = {
    id: number;
    take_no: string;
    status: string;
    branch: { id: number; name: string } | null;
    notes: string | null;
    counted_at: string | null;
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    items: Item[];
};

type Props = { take: Take };

export default function StockTakesShow({ take }: Props) {
    const { auth } = usePage().props as unknown as { auth: { user: { permissions: string[] } } };
    const canApprove = auth.user.permissions.includes('stock.take.approve');

    const [counted, setCounted] = useState<Record<number, string>>(
        Object.fromEntries(take.items.map((i) => [i.id, i.counted_quantity])),
    );
    const [reasons, setReasons] = useState<Record<number, string>>(
        Object.fromEntries(take.items.map((i) => [i.id, i.reason ?? ''])),
    );

    const diffs = (() => {
        const out: Record<string, number> = {};
        for (const item of take.items) {
            const c = Number(counted[item.id] ?? item.counted_quantity);
            out[item.id] = Math.round((c - Number(item.system_quantity)) * 1000) / 1000;
        }
        return out;
    })();

    return (
        <>
            <Head title={take.take_no} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={`${take.take_no} · ${take.branch?.name ?? ''}`}
                        description={
                            take.counted_at
                                ? `Counted ${formatDateTime(take.counted_at)} by ${take.creator?.name ?? '—'}`
                                : 'Counting in progress'
                        }
                    />
                    <div className="flex gap-2">
                        {take.status === 'pending' && canApprove && (
                            <Form {...StockTakeController.approve.form({ take: take.id })}>
                                <Button type="submit">Approve variances</Button>
                            </Form>
                        )}
                        {['counted', 'pending'].includes(take.status) && (
                            <Form {...StockTakeController.cancel.form({ take: take.id })}>
                                <Button variant="destructive" type="submit">Cancel</Button>
                            </Form>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Badge variant={take.status === 'approved' ? 'default' : take.status === 'cancelled' ? 'destructive' : 'outline'}>
                        {take.status}
                    </Badge>
                    {take.approver && <span className="text-muted-foreground text-sm">Approved by {take.approver.name}</span>}
                </div>

                {take.items.length === 0 ? (
                    <div className="rounded-xl border p-8 text-center text-muted-foreground">
                        No countable items in this branch.
                    </div>
                ) : take.status === 'counted' ? (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 text-sm font-semibold">Enter counted quantities</h3>
                        <Form {...StockTakeController.submitCounts.form({ take: take.id })} className="space-y-3">
                            {take.items.map((item, i) => (
                                <div key={item.id} className="grid grid-cols-5 items-center gap-2 rounded-lg border px-3 py-2">
                                    <div className="col-span-2">
                                        <div className="text-sm font-medium">{item.product?.name}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {item.product?.sku}
                                            {item.variant ? ` · ${item.variant.name}` : ''}
                                        </div>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        System: {Number(item.system_quantity)}
                                    </div>
                                    <input type="hidden" name={`items[${i}][id]`} value={item.id} />
                                    <Input
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        name={`items[${i}][counted_quantity]`}
                                        value={counted[item.id] ?? ''}
                                        onChange={(e) => setCounted((prev) => ({ ...prev, [item.id]: e.target.value }))}
                                    />
                                    <div className="flex flex-col gap-1">
                                        <span className={`text-sm font-medium ${diffs[item.id] !== 0 ? 'text-red-500' : 'text-muted-foreground'}`}>
                                            {diffs[item.id] > 0 ? `+${diffs[item.id]}` : diffs[item.id]}
                                        </span>
                                        <Input
                                            type="text"
                                            name={`items[${i}][reason]`}
                                            placeholder="Reason if variance"
                                            className="h-7 text-xs"
                                            value={reasons[item.id] ?? ''}
                                            onChange={(e) => setReasons((prev) => ({ ...prev, [item.id]: e.target.value }))}
                                        />
                                    </div>
                                </div>
                            ))}
                            <div className="flex justify-end pt-2">
                                <Button type="submit">Submit counts for approval</Button>
                            </div>
                        </Form>
                    </div>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead className="text-right">System</TableHead>
                                    <TableHead className="text-right">Counted</TableHead>
                                    <TableHead className="text-right">Variance</TableHead>
                                    <TableHead>Reason</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {take.items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <div className="font-medium">{item.product?.name}</div>
                                            <div className="text-muted-foreground text-xs">{item.product?.sku}</div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{Number(item.system_quantity)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{Number(item.counted_quantity)}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {Number(item.difference) !== 0 ? (
                                                <Badge variant="destructive">
                                                    {Number(item.difference) > 0 ? '+' : ''}{Number(item.difference)}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">0</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">{item.reason ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

StockTakesShow.layout = {
    breadcrumbs: [{ title: 'Stock Takes', href: stockTakes.index().url }],
};