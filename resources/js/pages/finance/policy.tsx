import { Form, Head } from '@inertiajs/react';
import FinancePolicyController from '@/actions/App/Http/Controllers/FinancePolicyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import finance from '@/routes/finance';

type Props = {
    discount_max_percent: number;
    discount_max_amount: number;
    variance_threshold: number;
    refund_auto_approve_limit: number;
    void_flag_amount: number;
    void_flag_daily_count: number;
    overdue_days_receivable: number;
};

export default function PolicyIndex(props: Props) {
    return (
        <>
            <Head title="Finance Policy" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Finance Policy"
                    description="Company-wide limits and thresholds enforced by the backend."
                />

                <div className="max-w-2xl rounded-xl border p-5">
                    <Form
                        {...FinancePolicyController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ errors }) => (
                            <>
                                <PolicyField
                                    label="Max discount per sale (%)"
                                    hint="Cashiers without the sell-at-price permission are capped here."
                                    name="discount_max_percent"
                                    defaultValue={props.discount_max_percent}
                                    error={errors?.discount_max_percent}
                                />
                                <PolicyField
                                    label="Max discount amount per sale"
                                    hint="0 disables the amount cap."
                                    name="discount_max_amount"
                                    defaultValue={props.discount_max_amount}
                                    error={errors?.discount_max_amount}
                                />
                                <PolicyField
                                    label="Shift variance threshold"
                                    hint="Any drawer variance above this flags and notifies Finance."
                                    name="variance_threshold"
                                    defaultValue={props.variance_threshold}
                                    error={errors?.variance_threshold}
                                />
                                <PolicyField
                                    label="Refund auto-approve limit"
                                    hint="Refunds at or below this amount skip the Finance approval queue."
                                    name="refund_auto_approve_limit"
                                    defaultValue={props.refund_auto_approve_limit}
                                    error={errors?.refund_auto_approve_limit}
                                />
                                <PolicyField
                                    label="Void flag amount"
                                    hint="Voided sales above this amount are flagged for review."
                                    name="void_flag_amount"
                                    defaultValue={props.void_flag_amount}
                                    error={errors?.void_flag_amount}
                                />
                                <PolicyField
                                    label="Void flag daily count"
                                    hint="More voids than this per cashier per day triggers the suspicious-void flag."
                                    name="void_flag_daily_count"
                                    defaultValue={props.void_flag_daily_count}
                                    error={errors?.void_flag_daily_count}
                                />
                                <PolicyField
                                    label="Receivable overdue days"
                                    hint="Credit balances older than this are reported as overdue AR."
                                    name="overdue_days_receivable"
                                    defaultValue={props.overdue_days_receivable}
                                    error={errors?.overdue_days_receivable}
                                />
                                <Button type="submit">Save policy</Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

function PolicyField({
    label,
    hint,
    name,
    defaultValue,
    error,
}: {
    label: string;
    hint: string;
    name: string;
    defaultValue: number;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type="number"
                min="0"
                step="0.01"
                defaultValue={defaultValue}
                required
            />
            <p className="text-muted-foreground text-xs">{hint}</p>
            <InputError message={error} />
        </div>
    );
}

PolicyIndex.layout = {
    breadcrumbs: [{ title: 'Finance Policy', href: finance.policy().url }],
};