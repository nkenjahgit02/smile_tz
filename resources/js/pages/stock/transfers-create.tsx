import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import StockTransferController from '@/actions/App/Http/Controllers/StockTransferController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import stockTransfers from '@/routes/stock-transfers';

type Branch = { id: number; name: string };
type Product = { id: number; name: string; sku: string; unit_id: number | null; variants: { id: number; name: string }[] };

type Line = {
    key: number;
    product_id: string;
    product_variant_id: string;
    variantName: string;
    quantity: string;
};

type Props = {
    branches: Branch[];
    products: Product[];
};

export default function StockTransfersCreate({ branches, products }: Props) {
    const [lines, setLines] = useState<Line[]>([{ key: 1, product_id: '', product_variant_id: '', variantName: '', quantity: '1' }]);

    const updateLine = (key: number, patch: Partial<Line>) => {
        setLines((prev) => prev.map((l) => (l.key === key ? { ...l, ...patch } : l)));
    };

    return (
        <>
            <Head title="New Stock Transfer" />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Heading
                    title="New Stock Transfer"
                    description="Choose source and destination branches, then add items."
                />

                <Form {...StockTransferController.store.form()} className="space-y-5" options={{ preserveScroll: true }}>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="from_branch_id">From branch</Label>
                            <select
                                id="from_branch_id"
                                name="from_branch_id"
                                required
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={branches[0]?.id ? String(branches[0].id) : ''}
                            >
                                {branches.map((b) => (
                                    <option key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="to_branch_id">To branch</Label>
                            <select
                                id="to_branch_id"
                                name="to_branch_id"
                                required
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={branches[1]?.id ? String(branches[1].id) : branches[0]?.id ?? ''}
                            >
                                {branches.map((b) => (
                                    <option key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Input id="notes" name="notes" placeholder="Optional" />
                    </div>

                    <div className="rounded-lg border">
                        <div className="grid grid-cols-4 gap-2 border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                            <span className="col-span-2">Product</span>
                            <span>Variant</span>
                            <span className="flex items-center justify-between">
                                Quantity <span />
                            </span>
                        </div>
                        {lines.map((line, i) => {
                            const base = `items[${i}]`;
                            const p = products.find((x) => String(x.id) === line.product_id);
                            return (
                                <div key={line.key} className="grid grid-cols-4 items-center gap-2 border-b px-3 py-2">
                                    <div className="col-span-2">
                                        <select
                                            name={`${base}[product_id]`}
                                            className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                            value={line.product_id}
                                            onChange={(e) => {
                                                const v = e.target.value;
                                                const newVariantId = products.find((x) => String(x.id) === v)?.variants[0];
                                                updateLine(line.key, {
                                                    product_id: v,
                                                    product_variant_id: newVariantId ? String(newVariantId.id) : '',
                                                });
                                            }}
                                        >
                                            <option value="">Select product…</option>
                                            {products.map((prod) => (
                                                <option key={prod.id} value={String(prod.id)}>
                                                    {prod.name} ({prod.sku})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <select
                                            name={`${base}[product_variant_id]`}
                                            className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                            value={line.product_variant_id}
                                            onChange={(e) => updateLine(line.key, { product_variant_id: e.target.value })}
                                            disabled={!p || p.variants.length === 0}
                                        >
                                            <option value="">No variant</option>
                                            {p?.variants.map((v) => (
                                                <option key={v.id} value={String(v.id)}>
                                                    {v.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="number"
                                            name={`${base}[quantity]`}
                                            step="0.001"
                                            min="0"
                                            value={line.quantity}
                                            onChange={(e) => updateLine(line.key, { quantity: e.target.value })}
                                        />
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
                                        {
                                            key: Math.max(0, ...prev.map((l) => l.key)) + 1,
                                            product_id: '',
                                            product_variant_id: '',
                                            variantName: '',
                                            quantity: '1',
                                        },
                                    ])
                                }
                            >
                                <Plus className="mr-1 h-4 w-4" /> Add item
                            </Button>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit">Create transfer (draft)</Button>
                    </div>
                </Form>
            </div>
        </>
    );
}

StockTransfersCreate.layout = {
    breadcrumbs: [
        { title: 'Stock Transfers', href: stockTransfers.index().url },
        { title: 'New', href: stockTransfers.create().url },
    ],
};