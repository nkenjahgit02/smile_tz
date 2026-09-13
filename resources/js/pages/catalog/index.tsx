import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import CatalogController from '@/actions/App/Http/Controllers/CatalogController';
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
import catalog from '@/routes/catalog';

type Category = { id: number; name: string; is_active: boolean; parent: { id: number; name: string } | null };
type Brand = { id: number; name: string; is_active: boolean };
type Unit = { id: number; name: string; abbreviation: string; is_active: boolean };

type Props = {
    categories: Category[];
    brands: Brand[];
    units: Unit[];
};

const tabs = [
    { key: 'categories', label: 'Categories' },
    { key: 'brands', label: 'Brands' },
    { key: 'units', label: 'Units' },
] as const;

type TabKey = (typeof tabs)[number]['key'];

export default function CatalogIndex({ categories, brands, units }: Props) {
    const [tab, setTab] = useState<TabKey>('categories');

    return (
        <>
            <Heading
                title="Catalog"
                description="Reference data for categories, brands and units of measure."
            />
            <div className="flex flex-col gap-6 p-4 pt-2">
                <div className="flex gap-2">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => setTab(t.key)}
                            className={
                                tab === t.key
                                    ? 'bg-primary text-primary-foreground rounded-md px-3 py-1.5 text-sm font-medium'
                                    : 'text-muted-foreground hover:bg-muted rounded-md px-3 py-1.5 text-sm font-medium'
                            }
                        >
                            {t.label} ({tab === t.key ? getCount(tab, { categories, brands, units }).length : ''})
                        </button>
                    ))}
                </div>

                {tab === 'categories' && <CategoriesPane categories={categories} />}
                {tab === 'brands' && <BrandsPane brands={brands} />}
                {tab === 'units' && <UnitsPane units={units} />}
            </div>
        </>
    );
}

function getCount(tab: TabKey, props: Props) {
    if (tab === 'categories') return props.categories;
    if (tab === 'brands') return props.brands;
    return props.units;
}

function CategoriesPane({ categories }: { categories: Category[] }) {
    const [open, setOpen] = useState(false);
    const [edit, setEdit] = useState<Category | null>(null);

    return (
        <div className="rounded-xl border">
            <div className="flex items-center justify-between border-b p-4">
                <p className="text-sm text-muted-foreground">
                    {categories.length} categories
                </p>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">Add category</Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add category</DialogTitle>
                            <DialogDescription>Create a product category.</DialogDescription>
                        </DialogHeader>
                        <CategoryForm categories={categories} onDone={() => setOpen(false)} />
                    </DialogContent>
                </Dialog>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Parent</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {categories.map((c) => (
                        <TableRow key={c.id}>
                            <TableCell className="font-medium">{c.name}</TableCell>
                            <TableCell>{c.parent?.name ?? '—'}</TableCell>
                            <TableCell>
                                <Badge variant={c.is_active ? 'outline' : 'secondary'}>
                                    {c.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-right">
                                <div className="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEdit(c)}
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(catalog.categories.destroy.url({ category: c.id }))
                                        }
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                    {categories.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                                No categories yet.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>

            <Dialog open={edit !== null} onOpenChange={(o) => !o && setEdit(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit category</DialogTitle>
                    </DialogHeader>
                    {edit && (
                        <CategoryForm
                            categories={categories}
                            category={edit}
                            onDone={() => setEdit(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

function CategoryForm({
    categories,
    category,
    onDone,
}: {
    categories: Category[];
    category?: Category;
    onDone: () => void;
}) {
    const controller = category
        ? CatalogController.updateCategory.form({ category: category.id })
        : CatalogController.storeCategory.form();

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={onDone}>
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" name="name" defaultValue={category?.name} required />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="parent_id">Parent category</Label>
                        <select
                            id="parent_id"
                            name="parent_id"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={category?.parent?.id ? String(category.parent.id) : ''}
                        >
                            <option value="">— None —</option>
                            {categories
                                .filter((c) => c.id !== category?.id)
                                .map((c) => (
                                    <option key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </option>
                                ))}
                        </select>
                        <InputError message={errors?.parent_id} />
                    </div>
                    {category && (
                        <Label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" defaultChecked={category.is_active} className="h-4 w-4" />
                            Active
                        </Label>
                    )}
                    <Button type="submit">{category ? 'Save' : 'Create'}</Button>
                </>
            )}
        </Form>
    );
}

function BrandsPane({ brands }: { brands: Brand[] }) {
    const [open, setOpen] = useState(false);
    const [edit, setEdit] = useState<Brand | null>(null);

    return (
        <div className="rounded-xl border">
            <div className="flex items-center justify-between border-b p-4">
                <p className="text-sm text-muted-foreground">{brands.length} brands</p>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">Add brand</Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add brand</DialogTitle>
                        </DialogHeader>
                        <BrandForm onDone={() => setOpen(false)} />
                    </DialogContent>
                </Dialog>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {brands.map((b) => (
                        <TableRow key={b.id}>
                            <TableCell className="font-medium">{b.name}</TableCell>
                            <TableCell>
                                <Badge variant={b.is_active ? 'outline' : 'secondary'}>
                                    {b.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-right">
                                <div className="flex justify-end gap-2">
                                    <Button variant="outline" size="sm" onClick={() => setEdit(b)}>
                                        Edit
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(catalog.brands.destroy.url({ brand: b.id }))
                                        }
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                    {brands.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={3} className="h-24 text-center text-muted-foreground">
                                No brands yet.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>

            <Dialog open={edit !== null} onOpenChange={(o) => !o && setEdit(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit brand</DialogTitle>
                    </DialogHeader>
                    {edit && <BrandForm brand={edit} onDone={() => setEdit(null)} />}
                </DialogContent>
            </Dialog>
        </div>
    );
}

function BrandForm({ brand, onDone }: { brand?: Brand; onDone: () => void }) {
    const controller = brand
        ? CatalogController.updateBrand.form({ brand: brand.id })
        : CatalogController.storeBrand.form();

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={onDone}>
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" name="name" defaultValue={brand?.name} required />
                        <InputError message={errors?.name} />
                    </div>
                    {brand && (
                        <Label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" defaultChecked={brand.is_active} className="h-4 w-4" />
                            Active
                        </Label>
                    )}
                    <Button type="submit">{brand ? 'Save' : 'Create'}</Button>
                </>
            )}
        </Form>
    );
}

function UnitsPane({ units }: { units: Unit[] }) {
    const [open, setOpen] = useState(false);
    const [edit, setEdit] = useState<Unit | null>(null);

    return (
        <div className="rounded-xl border">
            <div className="flex items-center justify-between border-b p-4">
                <p className="text-sm text-muted-foreground">{units.length} units</p>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">Add unit</Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add unit</DialogTitle>
                        </DialogHeader>
                        <UnitForm onDone={() => setOpen(false)} />
                    </DialogContent>
                </Dialog>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Abbreviation</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {units.map((u) => (
                        <TableRow key={u.id}>
                            <TableCell className="font-medium">{u.name}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{u.abbreviation}</Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant={u.is_active ? 'outline' : 'secondary'}>
                                    {u.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-right">
                                <div className="flex justify-end gap-2">
                                    <Button variant="outline" size="sm" onClick={() => setEdit(u)}>
                                        Edit
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(catalog.units.destroy.url({ unit: u.id }))
                                        }
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                    {units.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                                No units yet.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>

            <Dialog open={edit !== null} onOpenChange={(o) => !o && setEdit(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit unit</DialogTitle>
                    </DialogHeader>
                    {edit && <UnitForm unit={edit} onDone={() => setEdit(null)} />}
                </DialogContent>
            </Dialog>
        </div>
    );
}

function UnitForm({ unit, onDone }: { unit?: Unit; onDone: () => void }) {
    const controller = unit
        ? CatalogController.updateUnit.form({ unit: unit.id })
        : CatalogController.storeUnit.form();

    return (
        <Form {...controller} options={{ preserveScroll: true }} className="space-y-4" onSubmit={onDone}>
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" name="name" defaultValue={unit?.name} placeholder="e.g. Pieces" required />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="abbreviation">Abbreviation</Label>
                        <Input id="abbreviation" name="abbreviation" defaultValue={unit?.abbreviation} placeholder="e.g. pcs" required />
                        <InputError message={errors?.abbreviation} />
                    </div>
                    {unit && (
                        <Label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" defaultChecked={unit.is_active} className="h-4 w-4" />
                            Active
                        </Label>
                    )}
                    <Button type="submit">{unit ? 'Save' : 'Create'}</Button>
                </>
            )}
        </Form>
    );
}

CatalogIndex.layout = {
    breadcrumbs: [{ title: 'Catalog', href: catalog.index().url }],
};