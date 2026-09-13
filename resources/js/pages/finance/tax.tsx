import { Form, Head } from '@inertiajs/react';
import TaxController from '@/actions/App/Http/Controllers/TaxController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { formatMoney } from '@/lib/format';

type MonthlyRow = {
    month: string;
    revenue: number;
    vat: number;
};

type Props = {
    vat_enabled: boolean;
    vat_rate: number;
    months: MonthlyRow[];
};

export default function TaxIndex({ vat_enabled, vat_rate, months }: Props) {
    return (
        <>
            <Head title="Tax Settings" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Tax Settings"
                    description="Configure VAT settings and review the estimated VAT position."
                />

                <div className="max-w-xl rounded-xl border p-5">
                    <Form
                        {...TaxController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ errors }) => (
                            <>
                                <div className="flex items-center gap-2">
                                    <input
                                        id="vat_enabled"
                                        name="vat_enabled"
                                        type="checkbox"
                                        defaultChecked={vat_enabled}
                                        value="1"
                                    />
                                    <Label htmlFor="vat_enabled">Enable VAT</Label>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="vat_rate">VAT rate (%)</Label>
                                    <Input
                                        id="vat_rate"
                                        name="vat_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        defaultValue={vat_rate}
                                        required
                                    />
                                    <InputError message={errors?.vat_rate} />
                                </div>
                                <Button type="submit">Save tax settings</Button>
                            </>
                        )}
                    </Form>
                </div>

                <div>
                    <div className="mb-3 flex items-center gap-2">
                        <h2 className="text-sm font-semibold">Estimated VAT (12 months)</h2>
                        <Badge variant={vat_enabled ? 'default' : 'outline'}>
                            {vat_enabled ? `${vat_rate}% applied` : 'Disabled'}
                        </Badge>
                    </div>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Month</TableHead>
                                    <TableHead className="text-right">Revenue</TableHead>
                                    <TableHead className="text-right">VAT (est.)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {months.map((row) => (
                                    <TableRow key={row.month}>
                                        <TableCell>{row.month}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(row.revenue)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {vat_enabled ? formatMoney(row.vat) : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {months.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="text-muted-foreground h-16 text-center"
                                        >
                                            No sales revenue recorded.
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

TaxIndex.layout = {
    breadcrumbs: [{ title: 'Tax Settings', href: finance.tax().url }],
};