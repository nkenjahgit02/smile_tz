import { useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type SelectOption = {
    label: string;
    value: string;
};

export function SelectField({
    name,
    label,
    options,
    placeholder,
    defaultValue = '',
    error,
    className,
    id,
}: {
    name: string;
    label: string;
    options: SelectOption[];
    placeholder?: string;
    defaultValue?: string;
    error?: string;
    className?: string;
    id?: string;
}) {
    const [value, setValue] = useState(defaultValue);

    return (
        <div className="grid gap-2">
            <Label htmlFor={id ?? name}>{label}</Label>
            <input type="hidden" name={name} value={value} />
            <Select value={value} onValueChange={setValue}>
                <SelectTrigger id={id ?? name} className={className}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}