import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import LoyaltyRewardController from '@/actions/App/Http/Controllers/LoyaltyRewardController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import loyaltyRewards from '@/routes/loyalty-rewards';
import { formatNumber } from '@/lib/format';

type Reward = {
    id: number;
    name: string;
    points_cost: number;
    type: string;
    value: string | number;
    min_tier: string;
    is_active: boolean;
};

type Props = {
    rewards: {
        data: Reward[];
        current_page: number;
        last_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { status?: string };
};

const tierVariant: Record<
    string,
    'outline' | 'secondary' | 'destructive' | 'default'
> = {
    bronze: 'outline',
    silver: 'secondary',
    gold: 'default',
    platinum: 'destructive',
};

export default function LoyaltyIndex({
    rewards: rewardList,
    filters,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [edit, setEdit] = useState<Reward | null>(null);
    const [status, setStatus] = useState(filters.status ?? '');

    const applyWith = (params: Record<string, string>) => {
        router.get(loyaltyRewards.index().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Loyalty Rewards" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Loyalty Rewards"
                        description="Rewards customers can redeem with their loyalty points."
                    />
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>Add reward</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Add reward</DialogTitle>
                                <DialogDescription>
                                    Define a new redeemable reward.
                                </DialogDescription>
                            </DialogHeader>
                            <RewardForm onDone={() => setCreateOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="status-filter">Status</Label>
                        <select
                            id="status-filter"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                applyWith({ status: e.target.value });
                            }}
                        >
                            <option value="">All</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Reward</TableHead>
                                <TableHead className="text-right">Cost (points)</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-right">Value</TableHead>
                                <TableHead>Minimum tier</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rewardList.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-medium">
                                        {r.name}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(r.points_cost)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">{r.type}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {r.type === 'percentage'
                                            ? `${Number(r.value)}%`
                                            : Number(r.value)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={tierVariant[r.min_tier] ?? 'outline'}>
                                            {r.min_tier}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {r.is_active ? (
                                            <Badge variant="outline">Active</Badge>
                                        ) : (
                                            <Badge variant="destructive">Inactive</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setEdit(r)}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            `Delete reward "${r.name}"?`,
                                                        )
                                                    )
                                                        router.delete(
                                                            loyaltyRewards.destroy.url({
                                                                reward: r.id,
                                                            }),
                                                        );
                                                }}
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {rewardList.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No rewards found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {rewardList.last_page > 1 && (
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <span>
                            Page {rewardList.current_page} of {rewardList.last_page} ·{' '}
                            {rewardList.total} total
                        </span>
                        <div className="flex gap-2">
                            {rewardList.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <a href={rewardList.prev_page_url}>Previous</a>
                                </Button>
                            )}
                            {rewardList.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <a href={rewardList.next_page_url}>Next</a>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Dialog open={edit !== null} onOpenChange={(o) => !o && setEdit(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit reward</DialogTitle>
                        <DialogDescription>
                            Update reward details.
                        </DialogDescription>
                    </DialogHeader>
                    {edit && (
                        <RewardForm reward={edit} onDone={() => setEdit(null)} />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function RewardForm({
    reward,
    onDone,
}: {
    reward?: Reward;
    onDone: () => void;
}) {
    const controller = reward
        ? LoyaltyRewardController.update.form({ reward: reward.id })
        : LoyaltyRewardController.store.form();

    return (
        <Form
            {...controller}
            options={{ preserveScroll: true }}
            className="space-y-4"
            onSubmit={onDone}
        >
            {({ errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={reward?.name}
                            required
                        />
                        <InputError message={errors?.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="points_cost">Points cost</Label>
                            <Input
                                id="points_cost"
                                name="points_cost"
                                type="number"
                                min="1"
                                step="1"
                                defaultValue={reward?.points_cost ?? 0}
                                required
                            />
                            <InputError message={errors?.points_cost} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="type">Type</Label>
                            <select
                                id="type"
                                name="type"
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                defaultValue={reward?.type ?? 'points'}
                            >
                                <option value="points">Points voucher</option>
                                <option value="percentage">Percentage discount</option>
                                <option value="fixed">Fixed amount discount</option>
                            </select>
                            <InputError message={errors?.type} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="value">Value</Label>
                        <Input
                            id="value"
                            name="value"
                            type="number"
                            min="0.01"
                            step="0.01"
                            defaultValue={reward?.value ?? 0}
                            required
                        />
                        <InputError message={errors?.value} />
                        <p className="text-muted-foreground text-xs">
                            Percentage: discount % of the subtotal. Fixed: flat amount off.
                            Points: amount of bonus points granted.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="min_tier">Minimum tier</Label>
                        <select
                            id="min_tier"
                            name="min_tier"
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            defaultValue={reward?.min_tier ?? 'bronze'}
                        >
                            <option value="bronze">Bronze</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                        <InputError message={errors?.min_tier} />
                    </div>
                    <Label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="is_active"
                            defaultChecked={reward?.is_active ?? true}
                            className="h-4 w-4"
                        />
                        Active
                    </Label>
                    <Button type="submit">
                        {reward ? 'Save changes' : 'Create reward'}
                    </Button>
                </>
            )}
        </Form>
    );
}

LoyaltyIndex.layout = {
    breadcrumbs: [
        { title: 'Loyalty Rewards', href: loyaltyRewards.index().url },
    ],
};