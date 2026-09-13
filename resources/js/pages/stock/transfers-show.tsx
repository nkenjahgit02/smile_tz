import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
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
import StockTransferController from '@/actions/App/Http/Controllers/StockTransferController';
import stockTransfers from '@/routes/stock-transfers';
import { formatDate } from '@/lib/format';

type Item = {
    id: number;
    quantity: string;
    received_quantity: string | null;
    product: { id: number; name: string; sku: string } | null;
    variant: { id: number; name: string } | null;
};

type Transfer = {
    id: number;
    transfer_no: string;
    status: string;
    shipped_at: string | null;
    received_at: string | null;
    notes: string | null;
    from_branch: { id: number; name: string };
    to_branch: { id: number; name: string };
    creator: { id: number; name: string } | null;
    items: Item[];
};

type Props = { transfer: Transfer };

const statusVariant: Record<string, 'outline' | 'secondary' | 'destructive' | 'default'> = {
    draft: 'outline',
    in_transit: 'secondary',
    received: 'default',
    cancelled: 'destructive',
};

export default function StockTransfersShow({ transfer }: Props) {
    return (
        <>
            <Head title={transfer.transfer_no} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={transfer.transfer_no}
                        description={`${transfer.from_branch?.name} → ${transfer.to_branch?.name}`}
                    />
                    <div className="flex gap-2">
                        {transfer.status === 'draft' && (
                            <Form {...StockTransferController.ship.form({ transfer: transfer.id })}>
                                <Button variant="outline" type="submit">Ship</Button>
                            </Form>
                        )}
                        {transfer.status === 'in_transit' && (
                            <Form {...StockTransferController.receive.form({ transfer: transfer.id })}>
                                <Button type="submit">Receive into destination</Button>
                            </Form>
                        )}
                        {['draft', 'in_transit'].includes(transfer.status) && (
                            <Form {...StockTransferController.cancel.form({ transfer: transfer.id })}>
                                <Button variant="destructive" type="submit">Cancel</Button>
                            </Form>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-3 text-sm">
                    <Status info="Status" value={<Badge variant={statusVariant[transfer.status] ?? 'outline'}>{transfer.status}</Badge>} />
                    <Status info="Shipped" value={transfer.shipped_at ? formatDate(transfer.shipped_at) : '—'} />
                    <Status info="Received" value={transfer.received_at ? formatDate(transfer.received_at) : '—'} />
                    <Status info="Created by" value={transfer.creator?.name ?? '—'} />
                    <Status info="Notes" value={transfer.notes ?? '—'} />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Quantity</TableHead>
                                <TableHead>Received</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transfer.items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <div className="font-medium">{item.product?.name}</div>
                                        <div className="text-muted-foreground text-xs">{item.product?.sku}</div>
                                        {item.variant && <Badge variant="secondary">{item.variant.name}</Badge>}
                                    </TableCell>
                                    <TableCell>{Number(item.quantity)}</TableCell>
                                    <TableCell>{item.received_quantity !== null ? Number(item.received_quantity) : '—'}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

function Status({ info, value }: { info: string; value: React.ReactNode }) {
    return (
        <div className="rounded-lg border px-3 py-2">
            <div className="text-muted-foreground text-xs">{info}</div>
            <div>{value}</div>
        </div>
    );
}

StockTransfersShow.layout = {
    breadcrumbs: [
        { title: 'Stock Transfers', href: stockTransfers.index().url },
    ],
};