import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PurchaseOrderController from '@/actions/App/Http/Controllers/PurchaseOrderController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import purchaseOrders from '@/routes/purchase-orders';

type Supplier = { id: number; name: string };
type Branch = { id: number; name: string };

type Line = { key: number; product_id: string; quantity: string; unit_cost: string };

type Props = {
    suppliers: Supplier[];
    branches: Branch[];
};

export default function PurchaseOrdersCreate({ suppliers, branches }: Props) {
    const [lines, setLines] = useState<Line[]>([{ key: 1, product_id: '', quantity: '1', unit_cost: '' }]);

    const updateLine = (key: number, patch: Partial<Line>) => {
        setLines((prev) => prev.map((l) => (l.key === key ? { ...l, ...patch } : l)));
    };

    return (
        <>
            <Head title="New Purchase Order" />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Heading
                    title="New Purchase Order"
                    description="Enter supplier, destination branch and line items."
                />

                <Form
                    {...PurchaseOrderController.store.form()}
                    className="space-y-5"
                    options={{ preserveScroll: true }}
                >
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="supplier_id">Supplier</Label>
                            <select
                                id="supplier_id"
                                name="supplier_id"
                                required
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={suppliers[0]?.id ? String(suppliers[0].id) : ''}
                            >
                                <option value="">Select supplier…</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="branch_id">Receiving branch</Label>
                            <select
                                id="branch_id"
                                name="branch_id"
                                required
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={branches[0]?.id ? String(branches[0].id) : ''}
                            >
                                <option value="">Select branch…</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="expected_at">Expected arrival</Label>
                            <Input id="expected_at" name="expected_at" type="date" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Input id="notes" name="notes" placeholder="Optional" />
                        </div>
                    </div>

                    <div className="rounded-lg border">
                        <div className="grid grid-cols-12 gap-2 border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                            <span className="col-span-5">Product ID</span>
                            <span className="col-span-3">Quantity</span>
                            <span className="col-span-3">Unit cost</span>
                            <span />
                        </div>
                        {lines.map((line, i) => {
                            const base = `items[${i}]`;
                            return (
                                <div key={line.key} className="grid grid-cols-12 items-center gap-2 border-b px-3 py-2">
                                    <div className="col-span-5">
                                        <Input
                                            type="number"
                                            name={`${base}[product_id]`}
                                            placeholder="Product #"
                                            value={line.product_id}
                                            onChange={(e) => updateLine(line.key, { product_id: e.target.value })}
                                        />
                                    </div>
                                    <div className="col-span-3">
                                        <Input
                                            type="number"
                                            name={`${base}[quantity]`}
                                            step="0.001"
                                            min="0"
                                            value={line.quantity}
                                            onChange={(e) => updateLine(line.key, { quantity: e.target.value })}
                                        />
                                    </div>
                                    <div className="col-span-3">
                                        <Input
                                            type="number"
                                            name={`${base}[unit_cost]`}
                                            step="0.01"
                                            min="0"
                                            placeholder="Cost"
                                            value={line.unit_cost}
                                            onChange={(e) => updateLine(line.key, { unit_cost: e.target.value })}
                                        />
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setLines((prev) => prev.filter((l) => l.key !== line.key))}
                                        >
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                        <div className="p-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setLines((prev) => [
                                        ...prev,
                                        { key: Math.max(0, ...prev.map((l) => l.key)) + 1, product_id: '', quantity: '1', unit_cost: '' },
                                    ])
                                }
                            >
                                <Plus className="mr-1 h-4 w-4" /> Add line
                            </Button>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit">Create purchase order</Button>
                    </div>
                </Form>
            </div>
        </>
    );
}

PurchaseOrdersCreate.layout = {
    breadcrumbs: [
        { title: 'Purchase Orders', href: purchaseOrders.index().url },
        { title: 'New', href: purchaseOrders.create().url },
    ],
};