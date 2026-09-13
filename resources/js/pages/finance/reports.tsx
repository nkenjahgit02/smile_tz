import { Head, Link, router } from '@inertiajs/react';
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
import { exportMethod as reportExport } from '@/routes/finance/reports';
import finance from '@/routes/finance';
import { formatDate, formatMoney } from '@/lib/format';

type Props = {
    from: string;
    to: string;
    summary: {
        revenue: number;
        transactions: number;
        cogs: number;
        gross_profit: number;
        margin: number;
        refunds_count: number;
        refunds_amount: number;
    };
    can_view_margin: boolean;
    can_export: boolean;
    payment_split: { method: string; label: string; amount: number; percentage: number }[];
    daily: { day: string; revenue: number; transactions: number }[];
};

export default function ReportsIndex({ from, to, summary, can_view_margin, can_export, payment_split, daily }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(finance.reports().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Financial Reports" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Financial Reports"
                        description="Sales ledger, cost and payment-method breakdown for the selected period."
                    />
                    {can_export && (
                        <Button variant="outline" asChild>
                            <a href={`${reportExport().url}?from=${from}&to=${to}`} download>
                                Export CSV
                            </a>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            className="h-9 w-40"
                            defaultValue={from}
                            onChange={(e) => applyWith({ from: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            className="h-9 w-40"
                            defaultValue={to}
                            onChange={(e) => applyWith({ to: e.target.value })}
                        />
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-sm">Revenue</div>
                        <div className="text-2xl font-semibold tabular-nums">
                            {formatMoney(summary.revenue)}
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-sm">Transactions</div>
                        <div className="text-2xl font-semibold tabular-nums">
                            {summary.transactions}
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-sm">Gross profit</div>
                        <div className="text-2xl font-semibold tabular-nums">
                            {formatMoney(summary.gross_profit)}
                        </div>
                        {can_view_margin && (
                            <Badge className="mt-1">{summary.margin}% margin</Badge>
                        )}
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-sm">Refunds</div>
                        <div className="text-2xl font-semibold tabular-nums">
                            {formatMoney(summary.refunds_amount)}
                        </div>
                        <div className="text-muted-foreground text-sm">
                            {summary.refunds_count} transaction(s)
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border">
                        <div className="border-b p-4 text-sm font-semibold">Payment split</div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Method</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead className="text-right">Share</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payment_split.map((row) => (
                                    <TableRow key={row.method}>
                                        <TableCell>{row.label}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(row.amount)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.percentage}%
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {payment_split.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="text-muted-foreground h-16 text-center"
                                        >
                                            No payments in this period.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="rounded-xl border">
                        <div className="border-b p-4 text-sm font-semibold">Daily revenue</div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Day</TableHead>
                                    <TableHead className="text-right">Revenue</TableHead>
                                    <TableHead className="text-right">Sales</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {daily.map((row) => (
                                    <TableRow key={row.day}>
                                        <TableCell>{formatDate(row.day)}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(row.revenue)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.transactions}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {daily.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="text-muted-foreground h-16 text-center"
                                        >
                                            No sales in this period.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Financial Reports', href: finance.reports().url }],
};