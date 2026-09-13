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
import finance from '@/routes/finance';
import { formatDateTime, formatMoney } from '@/lib/format';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type VoidedSale = {
    id: number;
    sale_no: string;
    total: string | number;
    amount_paid: string | number;
    voided_at: string;
    is_void_flagged: boolean;
    branch: { id: number; name: string } | null;
    user: { id: number; name: string } | null;
    voidedBy: { id: number; name: string } | null;
};

type Props = {
    voids: Paginated<VoidedSale>;
    filters: { from?: string; to?: string; flagged?: string; search?: string };
};

export default function VoidsIndex({ voids, filters }: Props) {
    const applyWith = (params: Record<string, string>) => {
        router.get(finance.voids().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Void Audit" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Void Audit"
                    description="Review voided sales, including those flagged by the automated suspicious-void rules."
                />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            className="h-9 w-40"
                            defaultValue={filters.from ?? ''}
                            onChange={(e) => applyWith({ from: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            className="h-9 w-40"
                            defaultValue={filters.to ?? ''}
                            onChange={(e) => applyWith({ to: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            placeholder="Sale no."
                            className="h-9 w-44"
                            defaultValue={filters.search ?? ''}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    applyWith({ search: e.currentTarget.value });
                                }
                            }}
                        />
                    </div>
                    <label className="flex h-9 items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            defaultChecked={filters.flagged === '1'}
                            onChange={(e) => applyWith({ flagged: e.target.checked ? '1' : '' })}
                        />
                        Flagged only
                    </label>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Sale No.</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead className="text-right">Paid</TableHead>
                                <TableHead>Cashier</TableHead>
                                <TableHead>Voided By</TableHead>
                                <TableHead>Voided At</TableHead>
                                <TableHead>Flag</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {voids.data.map((v) => (
                                <TableRow key={v.id}>
                                    <TableCell className="font-mono text-sm font-medium">
                                        {v.sale_no}
                                    </TableCell>
                                    <TableCell>{v.branch?.name ?? '—'}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(v.total))}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(Number(v.amount_paid))}
                                    </TableCell>
                                    <TableCell>{v.user?.name ?? '—'}</TableCell>
                                    <TableCell>{v.voidedBy?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(v.voided_at)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={v.is_void_flagged ? 'destructive' : 'outline'}>
                                            {v.is_void_flagged ? 'Flagged' : '—'}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {voids.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No voided sales found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {voids.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {voids.current_page} of {voids.last_page} · {voids.total} total
                        </span>
                        <div className="flex gap-2">
                            {voids.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={voids.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {voids.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={voids.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

VoidsIndex.layout = {
    breadcrumbs: [{ title: 'Void Audit', href: finance.voids().url }],
};