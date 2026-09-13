import { Form, Head } from '@inertiajs/react';
import StockTakeController from '@/actions/App/Http/Controllers/StockTakeController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import stockTakes from '@/routes/stock-takes';

type Branch = { id: number; name: string };

type Props = { branches: Branch[] };

export default function StockTakesCreate({ branches }: Props) {
    return (
        <>
            <Head title="Start Stock Take" />
            <div className="mx-auto flex max-w-xl flex-col gap-6 p-4">
                <Heading
                    title="Start Stock Take"
                    description="Pick a branch. The system pre-fills current quantities for every product with stock."
                />
                <Form {...StockTakeController.commence.form()} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="branch_id">Branch</Label>
                        <select
                            id="branch_id"
                            name="branch_id"
                            required
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Select branch…</option>
                            {branches.map((b) => (
                                <option key={b.id} value={String(b.id)}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Input id="notes" name="notes" placeholder="Optional" />
                    </div>
                    <Button type="submit">Start counting</Button>
                </Form>
            </div>
        </>
    );
}

StockTakesCreate.layout = {
    breadcrumbs: [
        { title: 'Stock Takes', href: stockTakes.index().url },
        { title: 'Start', href: stockTakes.create().url },
    ],
};