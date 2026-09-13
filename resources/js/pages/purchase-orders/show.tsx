import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PurchaseOrderController from '@/actions/App/Http/Controllers/PurchaseOrderController';
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
import purchaseOrders from '@/routes/purchase-orders';
import { formatMoney, formatDate } from '@/lib/format';

type Item = {
    id: number;
    quantity: string;
    unit_cost: string;
    received_quantity: string;
    product: { id: number; name: string; sku: string } | null;
    variant: { id: number; name: string } | null;
};

type Order = {
    id: number;
    order_no: string;
    status: string;
    expected_at: string | null;
    received_at: string | null;
    notes: string | null;
    created_at: string;
    supplier: { id: number; name: string } | null;
    branch: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
    items: Item[];
};

type Props = {
    order: Order;
    canEdit: boolean;
};

export default function PurchaseOrdersShow({ order, canEdit }: Props) {
    const { auth } = usePage().props as unknown as { auth: { user: { permissions: string[] } } };
    const canApprove = auth.user.permissions.includes('purchase_orders.approve');

    const [received, setReceived] = useState<Record<number, string>>(
        Object.fromEntries(order.items.map((i) => [i.id, String(Number(i.quantity) - Number(i.received_quantity))])),
    );

    return (
        <>
            <Head title={order.order_no} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading title={order.order_no} description={`Supplier: ${order.supplier?.name ?? '—'}`} />
                    <div className="flex gap-2">
                        {order.status === 'draft' && canApprove && (
                            <Form
                                {...PurchaseOrderController.approve.form({ order: order.id })}
                            >
                                <Button variant="outline" type="submit">
                                    Approve
                                </Button>
                            </Form>
                        )}
                        {['draft', 'approved'].includes(order.status) && (
                            <Form {...PurchaseOrderController.cancel.form({ order: order.id })}>
                                <Button variant="destructive" type="submit">
                                    Cancel order
                                </Button>
                            </Form>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-3 text-sm">
                    <Info label="Status" value={<Badge variant="outline">{order.status}</Badge>} />
                    <Info label="Branch" value={order.branch?.name ?? '—'} />
                    <Info label="Expected" value={order.expected_at ? formatDate(order.expected_at) : '—'} />
                    <Info label="Received" value={order.received_at ? formatDate(order.received_at) : '—'} />
                    <Info label="Created by" value={order.creator?.name ?? '—'} />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Quantity</TableHead>
                                <TableHead>Unit Cost</TableHead>
                                <TableHead>Received</TableHead>
                                <TableHead>Line Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {order.items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <div className="font-medium">{item.product?.name ?? `Product #${item.product?.id ?? '?'}`}</div>
                                        <div className="text-muted-foreground text-xs">{item.product?.sku}</div>
                                        {item.variant && <Badge variant="secondary">{item.variant.name}</Badge>}
                                    </TableCell>
                                    <TableCell>{Number(item.quantity)}</TableCell>
                                    <TableCell>{formatMoney(Number(item.unit_cost))}</TableCell>
                                    <TableCell>{Number(item.received_quantity)}</TableCell>
                                    <TableCell className="font-medium">
                                        {formatMoney(Number(item.quantity) * Number(item.unit_cost))}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {order.items.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                        No line items.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {['draft', 'approved'].includes(order.status) && (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 text-sm font-semibold">Receive stock</h3>
                        <Form {...PurchaseOrderController.receive.form({ order: order.id })} className="space-y-3">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="batch_number">Batch number</Label>
                                    <Input id="batch_number" name="batch_number" placeholder="Optional" />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expiry_date">Expiry date</Label>
                                    <Input id="expiry_date" name="expiry_date" type="date" />
                                </div>
                            </div>
                            <div className="rounded-lg border">
                                {order.items.map((item, i) => (
                                    <div key={item.id} className="grid grid-cols-2 items-center gap-2 border-b px-3 py-2">
                                        <span className="text-sm">
                                            {item.product?.name}
                                            {item.variant ? ` (${item.variant.name})` : ''}
                                        </span>
                                        <Input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name={`items[${i}][received_quantity]`}
                                            value={received[item.id] ?? '0'}
                                            onChange={(e) =>
                                                setReceived((prev) => ({ ...prev, [item.id]: e.target.value }))
                                            }
                                        />
                                        <input type="hidden" name={`items[${i}][id]`} value={item.id} />
                                        <input type="hidden" name={`items[${i}][unit_cost]`} value={item.unit_cost} />
                                    </div>
                                ))}
                            </div>
                            <Button type="submit">Receive into stock</Button>
                        </Form>
                    </div>
                )}

                {canEdit && <EditSection order={order} />}
            </div>
        </>
    );
}

function Info({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="rounded-lg border px-3 py-2">
            <div className="text-muted-foreground text-xs">{label}</div>
            <div>{value}</div>
        </div>
    );
}

function EditSection({ order }: { order: Order }) {
    const [open, setOpen] = useState(false);

    if (!open) {
        return (
            <div>
                <Button variant="outline" onClick={() => setOpen(true)}>
                    Edit draft
                </Button>
            </div>
        );
    }

    return (
        <div className="rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">Edit draft</h3>
            <Form {...PurchaseOrderController.update.form({ order: order.id })} className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="supplier_id">Supplier</Label>
                        <Input id="supplier_id" name="supplier_id" type="number" defaultValue={order.supplier?.id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="branch_id">Branch</Label>
                        <Input id="branch_id" name="branch_id" type="number" defaultValue={order.branch?.id} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="expected_at">Expected</Label>
                        <Input id="expected_at" name="expected_at" type="date" defaultValue={order.expected_at ?? ''} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Input id="notes" name="notes" defaultValue={order.notes ?? ''} />
                    </div>
                </div>
                {order.items.map((item, i) => (
                    <div key={item.id} className="grid grid-cols-3 items-center gap-2">
                        <input type="hidden" name={`items[${i}][id]`} value={item.id} />
                        <input type="hidden" name={`items[${i}][product_id]`} value={item.product?.id ?? ''} />
                        <input type="hidden" name={`items[${i}][product_variant_id]`} value="" />
                        <span className="text-sm">{item.product?.name}</span>
                        <Input
                            type="number"
                            step="0.001"
                            name={`items[${i}][quantity]`}
                            defaultValue={item.quantity}
                        />
                        <Input
                            type="number"
                            step="0.01"
                            name={`items[${i}][unit_cost]`}
                            defaultValue={item.unit_cost}
                        />
                    </div>
                ))}
                <div className="flex justify-end gap-2">
                    <Button variant="outline" type="button" onClick={() => setOpen(false)}>
                        Close
                    </Button>
                    <Button type="submit">Save changes</Button>
                </div>
            </Form>
        </div>
    );
}

PurchaseOrdersShow.layout = {
    breadcrumbs: [
        { title: 'Purchase Orders', href: purchaseOrders.index().url },
    ],
};