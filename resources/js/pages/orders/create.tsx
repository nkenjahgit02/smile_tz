import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import orders from '@/routes/orders';

type Godown = { id: number; name: string; code: string };
type Product = { id: number; name: string; sku: string | null };

type Line = { key: number; product_id: string; quantity: string };

type Props = {
    godowns: Godown[];
    products: Product[];
};

export default function OrdersCreate({ godowns, products }: Props) {
    const [lines, setLines] = useState<Line[]>([
        { key: 1, product_id: '', quantity: '1' },
    ]);

    const updateLine = (key: number, patch: Partial<Line>) => {
        setLines((prev) =>
            prev.map((l) => (l.key === key ? { ...l, ...patch } : l)),
        );
    };

    return (
        <>
            <Head title="New Branch Order" />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Heading
                    title="New Branch Order"
                    description="Request stock from a Godown. The order goes through approval, picking and dispatch before stock moves."
                />

                <Form
                    {...OrderController.store.form()}
                    className="space-y-5"
                    options={{ preserveScroll: true }}
                >
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="godown_id">From Godown</Label>
                            <select
                                id="godown_id"
                                name="godown_id"
                                required
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={
                                    godowns[0]?.id ? String(godowns[0].id) : ''
                                }
                            >
                                <option value="">Select Godown…</option>
                                {godowns.map((g) => (
                                    <option key={g.id} value={String(g.id)}>
                                        {g.name} ({g.code})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="priority">Priority</Label>
                            <select
                                id="priority"
                                name="priority"
                                defaultValue="normal"
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="required_date">Required by</Label>
                            <Input
                                id="required_date"
                                name="required_date"
                                type="date"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Input
                                id="notes"
                                name="notes"
                                placeholder="Optional"
                            />
                        </div>
                    </div>

                    <div className="rounded-lg border">
                        <div className="text-muted-foreground grid grid-cols-12 gap-2 border-b px-3 py-2 text-xs font-medium">
                            <span className="col-span-8">Product</span>
                            <span className="col-span-3">Quantity</span>
                            <span />
                        </div>
                        {lines.map((line, i) => {
                            const base = `items[${i}]`;
                            return (
                                <div
                                    key={line.key}
                                    className="grid grid-cols-12 items-center gap-2 border-b px-3 py-2"
                                >
                                    <div className="col-span-8">
                                        <select
                                            name={`${base}[product_id]`}
                                            required
                                            className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                            value={line.product_id}
                                            onChange={(e) =>
                                                updateLine(line.key, {
                                                    product_id: e.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Select product…
                                            </option>
                                            {products.map((p) => (
                                                <option
                                                    key={p.id}
                                                    value={String(p.id)}
                                                >
                                                    {p.name}
                                                    {p.sku ? ` (${p.sku})` : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="col-span-3">
                                        <Input
                                            type="number"
                                            name={`${base}[quantity]`}
                                            step="0.001"
                                            min="0.001"
                                            required
                                            value={line.quantity}
                                            onChange={(e) =>
                                                updateLine(line.key, {
                                                    quantity: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={lines.length === 1}
                                            onClick={() =>
                                                setLines((prev) =>
                                                    prev.filter(
                                                        (l) =>
                                                            l.key !== line.key,
                                                    ),
                                                )
                                            }
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
                                        {
                                            key:
                                                Math.max(
                                                    0,
                                                    ...prev.map((l) => l.key),
                                                ) + 1,
                                            product_id: '',
                                            quantity: '1',
                                        },
                                    ])
                                }
                            >
                                <Plus className="mr-1 h-4 w-4" /> Add line
                            </Button>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit">Save draft order</Button>
                    </div>
                </Form>
            </div>
        </>
    );
}

OrdersCreate.layout = {
    breadcrumbs: [
        { title: 'Orders', href: orders.index().url },
        { title: 'New', href: orders.create().url },
    ],
};
