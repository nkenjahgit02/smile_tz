import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import sales from '@/routes/sales';
import { formatDateTime, formatMoney } from '@/lib/format';

type ReceiptPayload = {
    sale_no: string;
    receipt_no: string;
    sold_at: string;
    branch: {
        name: string;
        address: string | null;
        phone: string | null;
        currency: string;
    };
    cashier: { name: string };
    shift_no: string | null;
    customer: { name: string; phone: string | null } | null;
    items: {
        name: string | null;
        variant: string | null;
        quantity: number;
        original_unit_price: string | number;
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
};

const reductionLabels: Record<string, string> = {
    discount: 'Discount given to customer',
    loss: 'Sold at a loss',
    overpayment: 'Overpayment (change given)',
};

type Props = {
    sale_no: string;
    receipt: ReceiptPayload;
};

export default function SalesReceipt({ sale_no, receipt }: Props) {
    const currency = receipt.branch?.currency ?? 'TZS';

    useEffect(() => {
        const timer = window.setTimeout(() => window.print(), 300);
        return () => window.clearTimeout(timer);
    }, []);

    return (
        <>
            <Head title={`Receipt ${sale_no}`} />
            <div className="flex flex-col items-center gap-4 p-4">
                <div className="no-print flex w-full max-w-md items-center justify-between">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={sales.index().url}>Back to sales</Link>
                    </Button>
                    <Button size="sm" onClick={() => window.print()}>
                        Print
                    </Button>
                </div>

                <div className="receipt-sheet w-full max-w-md rounded-xl border p-6">
                    <div className="mb-4 text-center">
                        <p className="text-lg font-semibold">
                            {receipt.branch?.name}
                        </p>
                        {receipt.branch?.address && (
                            <p className="text-muted-foreground text-xs">
                                {receipt.branch.address}
                            </p>
                        )}
                        {receipt.branch?.phone && (
                            <p className="text-muted-foreground text-xs">
                                {receipt.branch.phone}
                            </p>
                        )}
                    </div>

                    <div className="text-muted-foreground mb-4 text-center font-mono text-xs">
                        <p>{receipt.receipt_no}</p>
                        {receipt.cashier && (
                            <p>Cashier: {receipt.cashier.name}</p>
                        )}
                        {receipt.shift_no && <p>Shift: {receipt.shift_no}</p>}
                        <p>{formatDateTime(receipt.sold_at)}</p>
                    </div>

                    {receipt.customer && (
                        <div className="text-muted-foreground mb-4 text-xs">
                            <p>{receipt.customer.name}</p>
                            {receipt.customer.phone && (
                                <p>{receipt.customer.phone}</p>
                            )}
                        </div>
                    )}

                    <div className="mb-4">
                        {receipt.items.map((item, i) => (
                            <div
                                key={i}
                                className="flex justify-between gap-3 py-1 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {item.name ?? 'Item'}
                                    </p>
                                    {item.variant && (
                                        <p className="text-muted-foreground text-xs">
                                            {item.variant} · ×
                                            {Number(item.quantity)}
                                        </p>
                                    )}
                                    {!item.variant && (
                                        <p className="text-muted-foreground text-xs">
                                            ×{Number(item.quantity)}
                                        </p>
                                    )}
                                </div>
                                <div className="text-right tabular-nums">
                                    {formatMoney(
                                        Number(item.line_total),
                                        currency,
                                    )}
                                    {Math.max(
                                        Number(item.original_unit_price) *
                                            Number(item.quantity) -
                                            Number(item.line_total),
                                        0,
                                    ) > 0 && (
                                        <span className="text-muted-foreground block text-xs">
                                            -
                                            {formatMoney(
                                                Math.max(
                                                    Number(
                                                        item.original_unit_price,
                                                    ) *
                                                        Number(item.quantity) -
                                                        Number(item.line_total),
                                                    0,
                                                ),
                                                currency,
                                            )}
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {receipt.coupons.length > 0 && (
                        <div className="mb-4 border-t pt-2">
                            {receipt.coupons.map((c, i) => (
                                <div
                                    key={i}
                                    className="text-muted-foreground flex justify-between text-sm"
                                >
                                    <span className="font-mono">{c.code}</span>
                                    <span className="tabular-nums">
                                        -
                                        {formatMoney(
                                            Number(c.discount_amount),
                                            currency,
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="mb-4 border-t pt-2 font-mono text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Subtotal
                            </span>
                            <span className="tabular-nums">
                                {formatMoney(
                                    Number(receipt.subtotal),
                                    currency,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Discounts
                            </span>
                            <span className="tabular-nums">
                                -
                                {formatMoney(
                                    Number(receipt.discount_total),
                                    currency,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Tax</span>
                            <span className="tabular-nums">
                                {formatMoney(
                                    Number(receipt.tax_total),
                                    currency,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between text-base font-semibold">
                            <span>Total</span>
                            <span className="tabular-nums">
                                {formatMoney(Number(receipt.total), currency)}
                            </span>
                        </div>
                    </div>

                    {receipt.has_price_reduction &&
                        Number(receipt.price_reduction_amount) > 0 && (
                            <div className="mb-4 border-t pt-2 font-mono text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">
                                        Discount
                                    </span>
                                    <span className="tabular-nums">
                                        -
                                        {formatMoney(
                                            Number(receipt.price_reduction_amount),
                                            currency,
                                        )}
                                    </span>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {reductionLabels[
                                        receipt.price_reduction_reason ??
                                            'discount'
                                    ] ?? 'Discount given to customer'}
                                </p>
                            </div>
                        )}

                    <div className="mb-4 font-mono text-sm">
                        {receipt.payments.map((p, i) => (
                            <div
                                key={i}
                                className="flex items-center justify-between text-sm"
                            >
                                <div className="flex items-center gap-2">
                                    <Badge variant="outline">{p.method}</Badge>
                                    {p.reference && (
                                        <span className="text-muted-foreground text-xs">
                                            {p.reference}
                                        </span>
                                    )}
                                </div>
                                <span className="tabular-nums">
                                    {formatMoney(Number(p.amount), currency)}
                                </span>
                            </div>
                        ))}
                    </div>

                    <div className="border-t pt-2 font-mono text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Amount paid
                            </span>
                            <span className="tabular-nums">
                                {formatMoney(
                                    Number(receipt.amount_paid),
                                    currency,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Change
                            </span>
                            <span className="tabular-nums">
                                {formatMoney(
                                    Number(receipt.change_amount),
                                    currency,
                                )}
                            </span>
                        </div>
                    </div>

                    <p className="text-muted-foreground mt-6 text-center text-xs">
                        Thank you for your purchase!
                    </p>
                </div>
            </div>
        </>
    );
}

SalesReceipt.layout = {
    breadcrumbs: [{ title: 'Sales', href: sales.index().url }],
};
