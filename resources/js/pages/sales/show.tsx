import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import SaleController from '@/actions/App/Http/Controllers/SaleController';
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
import { Textarea } from '@/components/ui/textarea';
import sales from '@/routes/sales';
import { formatDateTime, formatMoney } from '@/lib/format';

type SaleItem = {
    id: number;
    product: { name: string } | null;
    product_variant: { name: string } | null;
    quantity: number;
    original_unit_price: string | number;
    unit_price: string | number;
    discount: string | number;
    line_total: string | number;
    cost_price: string | number;
    profit: string | number;
    is_loss: boolean;
    loss_reason: string | null;
    price_overridden: boolean;
};

type Payment = {
    id: number;
    method: string;
    amount: string | number;
    reference: string | null;
};

type Sale = {
    id: number;
    sale_no: string;
    receipt_no: string;
    status: string;
    payment_status: string;
    subtotal: string | number;
    discount_total: string | number;
    tax_total: string | number;
    total: string | number;
    amount_paid: string | number;
    change_amount: string | number;
    has_price_reduction: boolean;
    price_reduction_amount: string | number;
    price_reduction_reason: string | null;
    void_reason: string | null;
    created_at: string;
    shift: { shift_no: string } | null;
    branch: { name: string; currency: string } | null;
    user: { name: string } | null;
    customer: { name: string; phone: string | null } | null;
    items: SaleItem[];
    payments: Payment[];
    coupons: { code: string; pivot: { discount_amount: string | number } }[];
};

type ReceiptPayload = {
    sale_no: string;
    receipt_no: string;
    sold_at: string;
    branch: {
        name: string;
        address: string | null;
        phone: string | null;
        currency: string;
    } | null;
    cashier: { name: string } | null;
    shift_no: string | null;
    customer: { name: string; phone: string | null } | null;
    items: {
        name: string | null;
        variant: string | null;
        quantity: number;
        unit_price: string | number;
        discount: string | number;
        line_total: string | number;
    }[];
    subtotal: string | number;
    discount_total: string | number;
    tax_total: string | number;
    total: string | number;
    coupons: { code: string; discount_amount: string | number }[];
    payments: {
        method: string;
        amount: string | number;
        reference: string | null;
    }[];
    amount_paid: string | number;
    change_amount: string | number;
    has_price_reduction: boolean;
    price_reduction_amount: string | number;
    price_reduction_reason: string | null;
    settings?: {
        paper_width_mm: number;
        header_lines: string[];
        footer_message: string;
        auto_print: boolean;
    } | null;
};

type Props = {
    sale: Sale;
    receipt: ReceiptPayload;
    can_void: boolean;
    just_completed: boolean;
};

const statusVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    active: 'default',
    voided: 'destructive',
    returned: 'secondary',
};

const paymentVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    paid: 'default',
    partial: 'secondary',
    unpaid: 'outline',
    refunded: 'secondary',
};

const reductionLabels: Record<string, string> = {
    discount: 'Discount given to customer',
    loss: 'Sold at a loss',
    overpayment: 'Overpayment (change given)',
};

export default function SalesShow({ sale, receipt, can_void, just_completed }: Props) {
    const [voidOpen, setVoidOpen] = useState(false);
    const [refundOpen, setRefundOpen] = useState(false);

    const currency = sale.branch?.currency ?? 'TZS';
    const isActive = sale.status === 'active';

    useEffect(() => {
        if (just_completed && receipt?.settings?.auto_print && isActive) {
            window.open(sales.receipt.url({ sale: sale.id }), '_blank');
        }
    }, [just_completed, receipt?.settings?.auto_print, isActive, sale.id]);

    return (
        <>
            <Head title={sale.sale_no} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={sale.sale_no}
                        description={
                            sale.receipt_no
                                ? `Receipt ${sale.receipt_no}`
                                : sale.created_at
                        }
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                window.open(
                                    sales.receipt.url({ sale: sale.id }),
                                    '_blank',
                                )
                            }
                        >
                            Print receipt
                        </Button>
                        {can_void && isActive && (
                            <>
                                <Dialog
                                    open={voidOpen}
                                    onOpenChange={setVoidOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button variant="destructive">
                                            Void sale
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Void {sale.sale_no}
                                            </DialogTitle>
                                            <DialogDescription>
                                                Provide a reason. This cannot be
                                                undone.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            {...SaleController.void.form({
                                                sale: sale.id,
                                            })}
                                            options={{ preserveScroll: true }}
                                            className="space-y-4"
                                            onSubmit={() => setVoidOpen(false)}
                                        >
                                            {({ errors }) => (
                                                <>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="reason">
                                                            Reason
                                                        </Label>
                                                        <Textarea
                                                            id="reason"
                                                            name="reason"
                                                            placeholder="Why is this sale being voided?"
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors?.reason
                                                            }
                                                        />
                                                    </div>
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                    >
                                                        Void sale
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                                <Dialog
                                    open={refundOpen}
                                    onOpenChange={setRefundOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button variant="secondary">
                                            Refund
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Refund {sale.sale_no}
                                            </DialogTitle>
                                            <DialogDescription>
                                                Refund up to{' '}
                                                {formatMoney(
                                                    Number(sale.amount_paid),
                                                    currency,
                                                )}
                                                .
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            {...SaleController.refund.form({
                                                sale: sale.id,
                                            })}
                                            options={{ preserveScroll: true }}
                                            className="space-y-4"
                                            onSubmit={() =>
                                                setRefundOpen(false)
                                            }
                                        >
                                            {({ errors }) => (
                                                <>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="amount">
                                                            Refund amount
                                                        </Label>
                                                        <Input
                                                            id="amount"
                                                            name="amount"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            max={Number(
                                                                sale.amount_paid,
                                                            )}
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors?.amount
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="reason">
                                                            Reason
                                                        </Label>
                                                        <Textarea
                                                            id="reason"
                                                            name="reason"
                                                            placeholder="Why is this being refunded?"
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors?.reason
                                                            }
                                                        />
                                                    </div>
                                                    <Label className="flex items-center gap-2 text-sm">
                                                        <input
                                                            type="checkbox"
                                                            name="restock"
                                                            className="h-4 w-4"
                                                        />
                                                        Restock returned units
                                                    </Label>
                                                    <Button
                                                        type="submit"
                                                        variant="secondary"
                                                    >
                                                        Process refund
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={statusVariant[sale.status] ?? 'outline'}>
                        {sale.status}
                    </Badge>
                    <Badge
                        variant={
                            paymentVariant[sale.payment_status] ?? 'outline'
                        }
                    >
                        {sale.payment_status}
                    </Badge>
                    {sale.void_reason && (
                        <span className="text-muted-foreground text-sm">
                            Void reason: {sale.void_reason}
                        </span>
                    )}
                </div>

                <div className="flex flex-wrap gap-3">
                    <div className="grid min-w-56 flex-1 gap-2 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium">
                            Sale
                        </p>
                        <p className="font-mono text-sm">{sale.sale_no}</p>
                        <p className="text-muted-foreground text-sm">
                            {formatDateTime(sale.created_at)}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {sale.branch?.name ?? '—'}
                            {sale.shift
                                ? ` · Shift ${sale.shift.shift_no}`
                                : ''}
                        </p>
                    </div>
                    <div className="grid min-w-56 flex-1 gap-2 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium">
                            Cashier
                        </p>
                        <p className="text-sm font-medium">
                            {sale.user?.name ?? '—'}
                        </p>
                    </div>
                    <div className="grid min-w-56 flex-1 gap-2 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium">
                            Customer
                        </p>
                        <p className="text-sm font-medium">
                            {sale.customer?.name ?? 'Walk-in'}
                        </p>
                        {sale.customer?.phone && (
                            <p className="text-muted-foreground text-sm">
                                {sale.customer.phone}
                            </p>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Item</TableHead>
                                <TableHead className="text-right">
                                    Qty
                                </TableHead>
                                <TableHead className="text-right">
                                    Unit Price
                                </TableHead>
                                <TableHead className="text-right">
                                    Discount
                                </TableHead>
                                <TableHead className="text-right">
                                    Line Total
                                </TableHead>
                                <TableHead className="text-right">
                                    Cost
                                </TableHead>
                                <TableHead className="text-right">
                                    Profit
                                </TableHead>
                                <TableHead>Notes</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sale.items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {item.product?.name ?? '—'}
                                        </div>
                                        {item.product_variant?.name && (
                                            <div className="text-muted-foreground text-xs">
                                                {item.product_variant.name}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {Number(item.quantity)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(
                                            Number(item.unit_price),
                                            currency,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(
                                            Math.max(
                                                Number(item.original_unit_price) *
                                                    Number(item.quantity) -
                                                    Number(item.line_total),
                                                0,
                                            ),
                                            currency,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(
                                            Number(item.line_total),
                                            currency,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(
                                            Number(item.cost_price),
                                            currency,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(
                                            Number(item.profit),
                                            currency,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {item.is_loss && (
                                            <Badge
                                                variant="destructive"
                                                className="mr-1"
                                            >
                                                Loss
                                            </Badge>
                                        )}
                                        {item.price_overridden && (
                                            <Badge variant="secondary">
                                                Overridden
                                            </Badge>
                                        )}
                                        {item.is_loss && item.loss_reason && (
                                            <span className="text-muted-foreground block">
                                                {item.loss_reason}
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="flex flex-col gap-4">
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Payment</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sale.payments.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {p.reference ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {p.method}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    Number(p.amount),
                                                    currency,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {sale.coupons.length > 0 && (
                            <div className="rounded-xl border p-4">
                                <p className="text-muted-foreground mb-2 text-xs font-medium">
                                    Coupons applied
                                </p>
                                {sale.coupons.map((c) => (
                                    <div
                                        key={c.code}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <span className="font-mono">
                                            {c.code}
                                        </span>
                                        <span className="tabular-nums">
                                            -
                                            {formatMoney(
                                                Number(c.pivot.discount_amount),
                                                currency,
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-2 rounded-xl border p-4">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">
                                Subtotal
                            </span>
                            <span className="tabular-nums">
                                {formatMoney(Number(sale.subtotal), currency)}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">
                                Discounts
                            </span>
                            <span className="tabular-nums">
                                -
                                {formatMoney(
                                    Number(sale.discount_total),
                                    currency,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Tax</span>
                            <span className="tabular-nums">
                                {formatMoney(Number(sale.tax_total), currency)}
                            </span>
                        </div>
                        <div className="flex justify-between border-t pt-2 text-sm font-medium">
                            <span>Total</span>
                            <span className="tabular-nums">
                                {formatMoney(Number(sale.total), currency)}
                            </span>
                        </div>
                        {sale.has_price_reduction &&
                            Number(sale.price_reduction_amount) > 0 && (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-2 py-1.5">
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium">
                                            Discount
                                        </span>
                                        <span className="tabular-nums text-destructive">
                                            -
                                            {formatMoney(
                                                Number(
                                                    sale.price_reduction_amount,
                                                ),
                                                currency,
                                            )}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        {reductionLabels[
                                            sale.price_reduction_reason ??
                                                'discount'
                                        ] ?? 'Discount given to customer'}
                                    </p>
                                </div>
                            )}
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">
                                Amount paid
                            </span>
                            <span className="tabular-nums">
                                {formatMoney(
                                    Number(sale.amount_paid),
                                    currency,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">
                                Change
                            </span>
                            <span className="tabular-nums">
                                {formatMoney(
                                    Number(sale.change_amount),
                                    currency,
                                )}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={sales.index().url}>Back to sales</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

SalesShow.layout = {
    breadcrumbs: [{ title: 'Sales', href: sales.index().url }],
};
