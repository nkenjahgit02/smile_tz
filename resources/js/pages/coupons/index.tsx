import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CouponController from '@/actions/App/Http/Controllers/CouponController';
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
import coupons from '@/routes/coupons';
import { formatDate, formatMoney } from '@/lib/format';

type Coupon = {
    id: number;
    code: string;
    description: string | null;
    type: string;
    value: string | number;
    min_spend: string | number;
    max_discount: string | number | null;
    usage_limit: number | null;
    used_count: number;
    is_active: boolean;
    valid_from: string | null;
    valid_to: string | null;
    created_at: string;
};

type Props = {
    coupons: {
        data: Coupon[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { search?: string };
};

export default function CouponsIndex({ coupons: couponList, filters }: Props) {
    const { auth } = usePage().props as unknown as {
        auth: { user: { permissions: string[] } };
    };
    const canManage = auth.user.permissions.includes('coupons.manage');

    const [createOpen, setCreateOpen] = useState(false);
    const [edit, setEdit] = useState<Coupon | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const applySearch = () => {
        router.get(
            coupons.index().url,
            { search: search || undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Coupons" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Coupons"
                        description="Discount codes customers can apply at checkout."
                    />
                    {canManage && (
                        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                            <DialogTrigger asChild>
                                <Button>Create coupon</Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>Create coupon</DialogTitle>
                                    <DialogDescription>
                                        Add a new discount code.
                                    </DialogDescription>
                                </DialogHeader>
                                <CouponForm
                                    onDone={() => setCreateOpen(false)}
                                />
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {canManage ? (
                    <div className="flex items-end gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="search">Search</Label>
                            <Input
                                id="search"
                                className="w-64"
                                placeholder="Coupon code…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applySearch()
                                }
                            />
                        </div>
                        <Button variant="outline" onClick={applySearch}>
                            Apply
                        </Button>
                    </div>
                ) : (
                    <div className="bg-muted/50 text-muted-foreground rounded-lg border p-4 text-sm">
                        You don't have permission to manage coupons.
                    </div>
                )}

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>Min Spend</TableHead>
                                <TableHead className="text-right">
                                    Usage
                                </TableHead>
                                <TableHead>Validity</TableHead>
                                <TableHead>Status</TableHead>
                                {canManage && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {couponList.data.map((c) => (
                                <TableRow key={c.id}>
                                    <TableCell className="font-mono text-sm font-medium">
                                        {c.code}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {c.description ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {c.type === 'percentage'
                                            ? `${Number(c.value)}%`
                                            : formatMoney(Number(c.value))}
                                        {c.max_discount !== null &&
                                            c.type === 'percentage' && (
                                                <span className="text-muted-foreground text-xs">
                                                    {' '}
                                                    up to{' '}
                                                    {formatMoney(
                                                        Number(c.max_discount),
                                                    )}
                                                </span>
                                            )}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {Number(c.min_spend) > 0
                                            ? formatMoney(Number(c.min_spend))
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {c.used_count}
                                        {c.usage_limit
                                            ? ` / ${c.usage_limit}`
                                            : ''}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {c.valid_from || c.valid_to
                                            ? `${c.valid_from ? formatDate(c.valid_from) : '…'} → ${
                                                  c.valid_to
                                                      ? formatDate(c.valid_to)
                                                      : '…'
                                              }`
                                            : 'Always'}
                                    </TableCell>
                                    <TableCell>
                                        {c.is_active ? (
                                            <Badge variant="default">
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </TableCell>
                                    {canManage && (
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
                                                        router.delete(
                                                            coupons.destroy.url(
                                                                {
                                                                    coupon: c.id,
                                                                },
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {couponList.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={canManage ? 8 : 7}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No coupons found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {couponList.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {couponList.current_page} of{' '}
                            {couponList.last_page} · {couponList.total} total
                        </span>
                        <div className="flex gap-2">
                            {couponList.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={couponList.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {couponList.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={couponList.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={edit !== null}
                onOpenChange={(o) => !o && setEdit(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit coupon</DialogTitle>
                        <DialogDescription>
                            Update coupon settings.
                        </DialogDescription>
                    </DialogHeader>
                    {edit && (
                        <CouponForm
                            coupon={edit}
                            onDone={() => setEdit(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function CouponForm({
    coupon,
    onDone,
}: {
    coupon?: Coupon;
    onDone: () => void;
}) {
    const controller = coupon
        ? CouponController.update.form({ coupon: coupon.id })
        : CouponController.store.form();

    return (
        <Form
            {...controller}
            options={{ preserveScroll: true }}
            className="space-y-4"
            onSubmit={onDone}
        >
            {({ errors }) => (
                <>
                    {!coupon && (
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                placeholder="e.g. SAVE10"
                                required
                            />
                            <InputError message={errors?.code} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            name="description"
                            defaultValue={coupon?.description ?? ''}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="type">Type</Label>
                            <select
                                id="type"
                                name="type"
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={coupon?.type ?? 'percentage'}
                            >
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed amount</option>
                            </select>
                            <InputError message={errors?.type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="value">Value</Label>
                            <Input
                                id="value"
                                name="value"
                                type="number"
                                min="0"
                                step="0.01"
                                defaultValue={coupon?.value ?? '0'}
                                required
                            />
                            <InputError message={errors?.value} />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="min_spend">Min spend</Label>
                            <Input
                                id="min_spend"
                                name="min_spend"
                                type="number"
                                min="0"
                                step="0.01"
                                defaultValue={coupon?.min_spend ?? '0'}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="max_discount">Max discount</Label>
                            <Input
                                id="max_discount"
                                name="max_discount"
                                type="number"
                                min="0"
                                step="0.01"
                                defaultValue={coupon?.max_discount ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="usage_limit">Usage limit</Label>
                            <Input
                                id="usage_limit"
                                name="usage_limit"
                                type="number"
                                min="1"
                                step="1"
                                placeholder="Unlimited"
                                defaultValue={coupon?.usage_limit ?? ''}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="valid_from">Valid from</Label>
                            <Input
                                id="valid_from"
                                name="valid_from"
                                type="date"
                                defaultValue={coupon?.valid_from ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="valid_to">Valid to</Label>
                            <Input
                                id="valid_to"
                                name="valid_to"
                                type="date"
                                defaultValue={coupon?.valid_to ?? ''}
                            />
                        </div>
                    </div>
                    {coupon && (
                        <Label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="is_active"
                                defaultChecked={coupon.is_active}
                                className="h-4 w-4"
                            />
                            Active
                        </Label>
                    )}
                    <Button type="submit">
                        {coupon ? 'Save changes' : 'Create coupon'}
                    </Button>
                </>
            )}
        </Form>
    );
}

CouponsIndex.layout = {
    breadcrumbs: [{ title: 'Coupons', href: coupons.index().url }],
};
