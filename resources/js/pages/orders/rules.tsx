import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import orders from '@/routes/orders';

type Rule = {
    id: number;
    name: string;
    threshold: number;
    unit: string;
    required_role: string;
    enabled: boolean;
};

type Props = {
    rules: Rule[];
};

export default function OrdersRules({ rules }: Props) {
    return (
        <>
            <Head title="Order Approval Rules" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Order Approval Rules"
                    description="Orders whose value reaches a threshold require a second approval by the configured role."
                />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Rule</TableHead>
                                <TableHead>Threshold (value)</TableHead>
                                <TableHead>Unit</TableHead>
                                <TableHead>Second approver role</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rules.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell>{r.name}</TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {r.threshold.toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {r.unit}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{r.required_role}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                r.enabled
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {r.enabled ? 'enabled' : 'disabled'}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {rules.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No approval rules configured.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

OrdersRules.layout = {
    breadcrumbs: [
        { title: 'Orders', href: orders.index().url },
        { title: 'Approval Rules', href: orders.rules().url },
    ],
};
