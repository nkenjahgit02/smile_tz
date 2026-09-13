import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import parkedSales from '@/routes/parked-sales';
import pos from '@/routes/pos';
import { api } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { toast } from 'sonner';

type ParkedSale = {
    id: number;
    parked_no: string;
    reference: string | null;
    held_at: string;
    customer: { name: string } | null;
    user: { name: string } | null;
    cart_json: { items: any[] };
};

type Props = {
    parked: {
        data: ParkedSale[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
};

export default function ParkedSalesIndex({ parked: parkedList }: Props) {
    const [recallingId, setRecallingId] = useState<number | null>(null);

    const recall = (p: ParkedSale) => {
        setRecallingId(p.id);
        (async () => {
            try {
                const recalled = await api<{
                    id: number;
                    parked_no: string;
                    reference: string | null;
                    customer_id: number | null;
                    items: any[];
                }>(parkedSales.recall.url({ parked: p.id }), 'POST');
                sessionStorage.setItem(
                    'recalled_parked_cart',
                    JSON.stringify({
                        customer_id: recalled.customer_id,
                        reference: recalled.reference,
                        items: recalled.items ?? [],
                    }),
                );
                router.visit(pos.index().url);
            } catch {
                setRecallingId(null);
                toast.error('Could not recall this parked sale');
            }
        })();
    };

    return (
        <>
            <Head title="Parked Sales" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">
                            Parked Sales
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Carts held at the POS terminal awaiting recall.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={pos.index().url}>Back to POS</Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Parked No.</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead>Held By</TableHead>
                                <TableHead>Held At</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {parkedList.data.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell className="font-mono text-sm font-medium">
                                        {p.parked_no}
                                    </TableCell>
                                    <TableCell>{p.reference ?? '—'}</TableCell>
                                    <TableCell>
                                        {p.customer?.name ?? 'Walk-in'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {(p.cart_json?.items ?? []).length}{' '}
                                            lines
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{p.user?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(p.held_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                disabled={recallingId !== null}
                                                onClick={() => recall(p)}
                                            >
                                                {recallingId === p.id
                                                    ? 'Recalling…'
                                                    : 'Recall'}
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() =>
                                                    router.delete(
                                                        parkedSales.destroy.url(
                                                            { parked: p.id },
                                                        ),
                                                    )
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {parkedList.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No parked sales found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {parkedList.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {parkedList.current_page} of{' '}
                            {parkedList.last_page} · {parkedList.total} total
                        </span>
                        <div className="flex gap-2">
                            {parkedList.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={parkedList.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {parkedList.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={parkedList.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ParkedSalesIndex.layout = {
    breadcrumbs: [{ title: 'Parked Sales', href: parkedSales.index().url }],
};
