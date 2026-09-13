import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import productRoutes from '@/routes/products';
import { formatMoney } from '@/lib/format';

type Category = { id: number; name: string };
type Brand = { id: number; name: string };
type Unit = { id: number; name: string; abbreviation: string };

type VariantRow = { id?: number; name: string; sku?: string; barcode?: string; godown_price?: string; shop_price?: string; _deleted?: boolean };
type ProductRow = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    godown_price: string;
    shop_price: string;
    reorder_level: number;
    has_expiry: boolean;
    is_active: boolean;
    category: { id: number; name: string } | null;
    brand: { id: number; name: string } | null;
    unit: { id: number; name: string; abbreviation: string } | null;
    variants: { id: number; name: string }[];
};

type Props = {
    products: {
        data: ProductRow[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    categories: Category[];
    brands: Brand[];
    units: Unit[];
    filters: { search?: string; category_id?: string; brand_id?: string; is_active?: string };
};

export default function ProductsIndex({ products, categories, brands, units, filters }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [edit, setEdit] = useState<ProductRow | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const applyWith = (params: Record<string, string>) => {
        router.get(productRoutes.index().url, params, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Products" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Products"
                        description="Catalogue with godown and shop pricing, plus variants."
                    />
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>New product</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>New product</DialogTitle>
                                <DialogDescription>
                                    Prices: Godown (wholesale/floor) and Shop (retail counter).
                                </DialogDescription>
                            </DialogHeader>
                            <ProductForm
                                categories={categories}
                                brands={brands}
                                units={units}
                                onDone={() => setCreateOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            className="w-56"
                            placeholder="Name, SKU or barcode…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyWith({ search })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cat-filter">Category</Label>
                        <select
                            id="cat-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.category_id ?? ''}
                            onChange={(e) => applyWith({ category_id: e.target.value === 'all' ? '' : e.target.value })}
                        >
                            <option value="all">All categories</option>
                            {categories.map((c) => (
                                <option key={c.id} value={String(c.id)}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="brand-filter">Brand</Label>
                        <select
                            id="brand-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={filters.brand_id ?? ''}
                            onChange={(e) => applyWith({ brand_id: e.target.value === 'all' ? '' : e.target.value })}
                        >
                            <option value="all">All brands</option>
                            {brands.map((b) => (
                                <option key={b.id} value={String(b.id)}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button variant="outline" onClick={() => applyWith({ search })}>
                        Apply
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Godown Price</TableHead>
                                <TableHead>Shop Price</TableHead>
                                <TableHead>Variants</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell>
                                        <div className="font-medium">{p.name}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {p.sku}
                                            {p.barcode ? ` · ${p.barcode}` : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {p.category?.name ?? '—'}
                                        {p.brand?.name ? ` / ${p.brand.name}` : ''}
                                    </TableCell>
                                    <TableCell>{formatMoney(Number(p.godown_price))}</TableCell>
                                    <TableCell>{formatMoney(Number(p.shop_price))}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{p.variants.length}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={p.is_active ? 'outline' : 'secondary'}>
                                            {p.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="outline" size="sm" onClick={() => setEdit(p)}>
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {products.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                        No products found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {products.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {products.current_page} of {products.last_page} · {products.total} total
                        </span>
                        <div className="flex gap-2">
                            {products.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={products.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {products.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={products.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Dialog open={edit !== null} onOpenChange={(o) => !o && setEdit(null)}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit product</DialogTitle>
                    </DialogHeader>
                    {edit && (
                        <ProductForm
                            product={edit}
                            categories={categories}
                            brands={brands}
                            units={units}
                            onDone={() => setEdit(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

type EditableVariant = Omit<VariantRow, 'id'> & { id?: number; key: number };

function ProductForm({
    product,
    categories,
    brands,
    units,
    onDone,
}: {
    product?: ProductRow;
    categories: Category[];
    brands: Brand[];
    units: Unit[];
    onDone: () => void;
}) {
    const controller = product
        ? ProductController.update.form({ product: product.id })
        : ProductController.store.form();

    const [variants, setVariants] = useState<EditableVariant[]>(
        (product?.variants.map((v) => ({ id: v.id, name: v.name, key: v.id })) as EditableVariant[]) ?? [],
    );

    const updateVariant = (key: number, patch: Partial<EditableVariant>) => {
        setVariants((prev) => prev.map((v) => (v.key === key ? { ...v, ...patch } : v)));
    };

    const removeVariant = (key: number) => {
        setVariants((prev) =>
            prev.map((v) => (v.key === key ? { ...v, _deleted: true } : v)),
        );
    };

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={onDone}>
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Product name</Label>
                        <Input id="name" name="name" defaultValue={product?.name} required />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="sku">SKU</Label>
                            <Input id="sku" name="sku" defaultValue={product?.sku} required />
                            <InputError message={errors?.sku} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="barcode">Barcode</Label>
                            <Input id="barcode" name="barcode" defaultValue={product?.barcode ?? ''} />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="category_id">Category</Label>
                            <select
                                id="category_id"
                                name="category_id"
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={product?.category?.id ? String(product.category.id) : ''}
                            >
                                <option value="">— None —</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="brand_id">Brand</Label>
                            <select
                                id="brand_id"
                                name="brand_id"
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={product?.brand?.id ? String(product.brand.id) : ''}
                            >
                                <option value="">— None —</option>
                                {brands.map((b) => (
                                    <option key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="unit_id">Unit</Label>
                            <select
                                id="unit_id"
                                name="unit_id"
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={product?.unit?.id ? String(product.unit.id) : ''}
                            >
                                <option value="">— None —</option>
                                {units.map((u) => (
                                    <option key={u.id} value={String(u.id)}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="godown_price">Godown price</Label>
                            <Input
                                id="godown_price"
                                name="godown_price"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={product?.godown_price ?? '0'}
                                required
                            />
                            <InputError message={errors?.godown_price} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="shop_price">Shop price</Label>
                            <Input
                                id="shop_price"
                                name="shop_price"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={product?.shop_price ?? '0'}
                            />
                            <InputError message={errors?.shop_price} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="reorder_level">Reorder level</Label>
                            <Input
                                id="reorder_level"
                                name="reorder_level"
                                type="number"
                                min="0"
                                defaultValue={product?.reorder_level ?? '0'}
                            />
                        </div>
                        <div className="flex items-end gap-4 pb-1">
                            <Label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="has_expiry" defaultChecked={product?.has_expiry ?? false} className="h-4 w-4" />
                                Tracks expiry
                            </Label>
                            <Label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_active" defaultChecked={product?.is_active ?? true} className="h-4 w-4" />
                                Active
                            </Label>
                        </div>
                    </div>

                    <div className="grid gap-2 rounded-lg border p-3">
                        <div className="flex items-center justify-between">
                            <Label className="font-medium">Variants</Label>
<Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setVariants((prev) => [
                                            ...prev,
                                            { name: '', key: Math.max(0, ...prev.map((v) => v.key)) + 1 },
                                        ])
                                    }
                                >
                                    <Plus className="mr-1 h-3.5 w-3.5" /> Add variant
                                </Button>
                            </div>
                            {variants.length === 0 && (
                                <p className="text-muted-foreground py-2 text-xs">
                                    No variants — this product sells as a single unit.
                                </p>
                            )}
                            {variants.map((v) => (
                                <VariantRow
                                    key={v.key}
                                    index={variants.indexOf(v)}
                                    variant={v}
                                    disabled={!!v._deleted}
                                    onChange={updateVariant}
                                    onRemove={() => removeVariant(v.key)}
                                />
                            ))}
                        </div>

                    <Button type="submit">{product ? 'Save changes' : 'Create product'}</Button>
                </>
            )}
        </Form>
    );
}

function VariantRow({
    variant,
    index,
    onChange,
    onRemove,
    disabled,
}: {
    variant: EditableVariant;
    index: number;
    onChange: (key: number, patch: Partial<EditableVariant>) => void;
    onRemove: () => void;
    disabled: boolean;
}) {
    const base = `variants[${index}]`;

    return (
        <div className="grid grid-cols-8 items-center gap-2">
            {typeof variant.id === 'number' && (
                <input type="hidden" name={`${base}[id]`} value={variant.id} />
            )}
            <input type="hidden" name={`${base}[_deleted]`} value={disabled ? '1' : '0'} />
            <div className="col-span-2">
                <input
                    name={`${base}[name]`}
                    className="border-input h-8 w-full rounded-md border bg-transparent px-2 text-sm"
                    placeholder="Name (e.g. 500ml)"
                    value={variant.name}
                    disabled={disabled}
                    onChange={(e) => onChange(variant.key, { name: e.target.value })}
                />
            </div>
            <div className="col-span-2">
                <input
                    name={`${base}[sku]`}
                    className="border-input h-8 w-full rounded-md border bg-transparent px-2 text-sm"
                    placeholder="SKU"
                    value={variant.sku ?? ''}
                    disabled={disabled}
                    onChange={(e) => onChange(variant.key, { sku: e.target.value })}
                />
            </div>
            <div>
                <input
                    name={`${base}[godown_price]`}
                    className="border-input h-8 w-full rounded-md border bg-transparent px-2 text-sm"
                    placeholder="Godown"
                    type="number"
                    step="0.01"
                    min="0"
                    value={variant.godown_price ?? ''}
                    disabled={disabled}
                    onChange={(e) => onChange(variant.key, { godown_price: e.target.value })}
                />
            </div>
            <div>
                <input
                    name={`${base}[shop_price]`}
                    className="border-input h-8 w-full rounded-md border bg-transparent px-2 text-sm"
                    placeholder="Shop"
                    type="number"
                    step="0.01"
                    min="0"
                    value={variant.shop_price ?? ''}
                    disabled={disabled}
                    onChange={(e) => onChange(variant.key, { shop_price: e.target.value })}
                />
            </div>
            <div>
                <input
                    name={`${base}[barcode]`}
                    className="border-input h-8 w-full rounded-md border bg-transparent px-2 text-sm"
                    placeholder="Barcode"
                    value={variant.barcode ?? ''}
                    disabled={disabled}
                    onChange={(e) => onChange(variant.key, { barcode: e.target.value })}
                />
            </div>
            <div className="flex justify-end">
                <Button type="button" variant="ghost" size="icon" disabled={disabled} onClick={onRemove}>
                    <Trash2 className="h-4 w-4 text-red-500" />
                </Button>
            </div>
        </div>
    );
}