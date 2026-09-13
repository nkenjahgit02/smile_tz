import { Form, Head } from '@inertiajs/react';
import ReceiptSettingsController from '@/actions/App/Http/Controllers/ReceiptSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    paper_width: number;
    header_lines: string;
    footer_message: string;
    auto_print: boolean;
};

export default function ReceiptSettings({
    paper_width,
    header_lines,
    footer_message,
    auto_print,
}: Props) {
    return (
        <>
            <Head title="Receipt Printer" />
            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Receipt Printer"
                    description="Configure how sales receipts are printed on the thermal receipt printer."
                />

                <div className="max-w-xl rounded-xl border p-5">
                    <Form
                        {...ReceiptSettingsController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="paper_width">
                                        Paper width (mm)
                                    </Label>
                                    <Select
                                        name="paper_width"
                                        defaultValue={String(paper_width)}
                                    >
                                        <SelectTrigger id="paper_width">
                                            <SelectValue placeholder="Select paper width" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="58">58 mm</SelectItem>
                                            <SelectItem value="76">76 mm</SelectItem>
                                            <SelectItem value="80">80 mm</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors?.paper_width} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="header_lines">
                                        Header lines
                                    </Label>
                                    <Textarea
                                        id="header_lines"
                                        name="header_lines"
                                        rows={3}
                                        defaultValue={header_lines}
                                        placeholder={
                                            'One line per row, e.g.\nTIN: 123-456-789\nP.O. Box 1234, Dar es Salaam'
                                        }
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Printed at the top of every receipt, one line
                                        per row.
                                    </p>
                                    <InputError message={errors?.header_lines} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="footer_message">
                                        Footer message
                                    </Label>
                                    <Textarea
                                        id="footer_message"
                                        name="footer_message"
                                        rows={2}
                                        defaultValue={footer_message}
                                    />
                                    <InputError message={errors?.footer_message} />
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        id="auto_print"
                                        name="auto_print"
                                        type="checkbox"
                                        defaultChecked={auto_print}
                                        value="1"
                                    />
                                    <Label htmlFor="auto_print">
                                        Open the print dialog automatically after
                                        checkout
                                    </Label>
                                </div>

                                <Button type="submit">Save receipt settings</Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}