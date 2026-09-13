import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Gift, Minus, Plus, ScanBarcode, Search, ShoppingCart, Trash2, X, UserCheck, Tag, CreditCard, PauseCircle } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import sales from '@/routes/sales';
import parkedSales from '@/routes/parked-sales';
import { api } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import { toast } from 'sonner';

type Props = {
    branch: { id: number; name: string; code: string; currency: string };
    active_shift: { id: number; shift_no: string; opening_float: string } | null;
    can: { price_override: boolean; discount: boolean; layaway: boolean; park: boolean };
};

type LookupProduct = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    unit: string | null;
    shop_price: number;
    average_cost: number;
    stock_qty: number;
    default_variant_id: number | null;
    variants: { id: number; name: string; sku: string; barcode: string | null; price: number; average_cost: number; stock_qty: number }[];
};

type CartItem = {
    key: number;
    product_id: number;
    variant_id: number | null;
    name: string;
    variant_name: string | null;
    unit_price: number;
    catalog_price: number;
    average_cost: number;
    stock_qty: number;
    quantity: number;
    discount: number;
    override_reason: string;
    requires_override_reason: boolean;
    likely_loss: boolean;
    loss_reason: string;
};

type CustomerRow = {
    id: number;
    customer_no: string;
    name: string;
    phone: string | null;
    customer_type: string;
    loyalty_tier: string;
    loyalty_points: number;
    deposit_balance: number;
    credit_balance: number;
    credit_limit: number;
};

type RewardOption = {
    id: number;
    name: string;
    points_cost: number;
    type: string;
    value: number;
    min_tier: string;
};

type PaymentRow = {
    key: number;
    method: 'cash' | 'card' | 'mobile' | 'bank_transfer' | 'loyalty' | 'credit' | 'deposit';
    amount: string;
    reference: string;
};

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'mobile', label: 'Mobile Money' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
] as const;

let keyCounter = 1;
const nextKey = () => keyCounter++;

export default function Pos({ branch, active_shift, can }: Props) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<LookupProduct[]>([]);
    const [searching, setSearching] = useState(false);
    const [variantChoice, setVariantChoice] = useState<LookupProduct | null>(null);
    const [cart, setCart] = useState<CartItem[]>([]);
    const [customerName, setCustomerName] = useState('');
    const [customerId, setCustomerId] = useState<number | null>(null);
    const [customerDetails, setCustomerDetails] = useState<CustomerRow | null>(null);
    const [customerResults, setCustomerResults] = useState<CustomerRow[]>([]);
    const [customerSearch, setCustomerSearch] = useState('');
    const [couponCode, setCouponCode] = useState('');
    const [couponApplied, setCouponApplied] = useState<{ code: string; discount: number } | null>(null);
    const [couponBusy, setCouponBusy] = useState(false);
    const [rewardOptions, setRewardOptions] = useState<RewardOption[]>([]);
    const [rewardId, setRewardId] = useState<number | null>(null);
    const [payments, setPayments] = useState<PaymentRow[]>([{ key: nextKey(), method: 'cash', amount: '', reference: '' }]);
    const [layawayDeposit, setLayawayDeposit] = useState('');
    const [layawayMode, setLayawayMode] = useState(false);
    const [payDialogOpen, setPayDialogOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [reductionReason, setReductionReason] = useState<'' | 'discount' | 'loss' | 'overpayment'>('');
    const scannerRef = useRef<HTMLInputElement>(null);

    // Keyboard shortcut: "/" or Shift+Ctrl+K focuses the scanner[cite: 1].
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === '/' && document.activeElement?.tagName !== 'INPUT') {
                e.preventDefault();
                scannerRef.current?.focus();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    // Debounced lookup[cite: 1].
    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);
            return;
        }
        setSearching(true);
        const t = setTimeout(async () => {
            try {
                const incoming = await api<LookupProduct[]>('/products/lookup', 'POST', { q: query });
                setResults(incoming);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 250);
        return () => clearTimeout(t);
    }, [query]);

    // Debounced customer search[cite: 1].
    useEffect(() => {
        if (!customerSearch.trim()) {
            setCustomerResults([]);
            return;
        }
        const t = setTimeout(async () => {
            try {
                const incoming = await api<CustomerRow[]>(`/customers/search?q=${encodeURIComponent(customerSearch)}`, 'GET');
                setCustomerResults(incoming);
            } catch {
                setCustomerResults([]);
            }
        }, 250);
        return () => clearTimeout(t);
    }, [customerSearch]);

    // Load redeemable rewards when a customer is selected.
    useEffect(() => {
        if (!customerId) {
            setRewardOptions([]);
            setRewardId(null);
            return;
        }
        (async () => {
            try {
                const incoming = await api<RewardOption[]>('/loyalty-rewards/options', 'GET');
                setRewardOptions(incoming);
            } catch {
                setRewardOptions([]);
            }
        })();
    }, [customerId]);

    // Restore a parked sale[cite: 1].
    useEffect(() => {
        const raw = sessionStorage.getItem('recalled_parked_cart');
        if (!raw) return;
        sessionStorage.removeItem('recalled_parked_cart');

        try {
            const recalled = JSON.parse(raw) as {
                customer_id: number | null;
                reference: string | null;
                items: Array<{
                    product_id: number;
                    product_variant_id: number | null;
                    name: string;
                    variant_name: string | null;
                    quantity: number;
                    unit_price: number;
                    catalog_price: number;
                    discount: number;
                    average_cost: number;
                }>;
            };

            if (recalled.customer_id) {
                setCustomerId(recalled.customer_id);
                setCustomerName(recalled.reference ?? String(recalled.customer_id));
                setCustomerSearch(recalled.reference ?? '');
            }

            if (recalled.items.length > 0) {
                setCart(recalled.items.map((i) => ({
                    key: nextKey(),
                    product_id: i.product_id,
                    variant_id: i.product_variant_id,
                    name: i.name,
                    variant_name: i.variant_name,
                    unit_price: i.unit_price,
                    catalog_price: i.catalog_price,
                    average_cost: i.average_cost,
                    quantity: i.quantity,
                    discount: i.discount,
                    override_reason: '',
                    requires_override_reason: Math.abs(i.unit_price - i.catalog_price) > 0.005,
                    likely_loss: i.average_cost > 0 && i.unit_price < i.average_cost,
                    loss_reason: '',
                    stock_qty: i.quantity,
                })));
                toast.success('Recalled parked sale');
            }
        } catch {
            // Ignore malformed recall data.
        }
    }, []);

    const addToCart = (p: LookupProduct, variantId: number | null = null) => {
        const variant = variantId ? p.variants.find((v) => v.id === variantId) : null;
        const price = (variant?.price ?? p.shop_price) || 0;
        const cost = variant?.average_cost ?? p.average_cost;

        setCart((prev) => {
            const existing = prev.find(
                (c) => c.product_id === p.id && (c.variant_id ?? null) === (variantId ?? null),
            );
            if (existing) {
                return prev.map((c) =>
                    c.key === existing.key ? { ...c, quantity: c.quantity + 1 } : c,
                );
            }
            return [
                ...prev,
                {
                    key: nextKey(),
                    product_id: p.id,
                    variant_id: variantId,
                    name: p.name,
                    variant_name: variant?.name ?? null,
                    unit_price: price,
                    catalog_price: price,
                    average_cost: cost,
                    stock_qty: variant?.stock_qty ?? p.stock_qty,
                    quantity: 1,
                    discount: 0,
                    override_reason: '',
                    requires_override_reason: false,
                    likely_loss: cost > 0 && price < cost,
                    loss_reason: '',
                },
            ];
        });

        setQuery('');
        setResults([]);
        scannerRef.current?.focus();
    };

    const handleScan = (p: LookupProduct) => {
        if (p.variants.length > 1) {
            setVariantChoice(p);
        } else {
            addToCart(p, p.default_variant_id);
        }
    };

    const updateItem = (key: number, patch: Partial<CartItem>) => {
        setCart((prev) =>
            prev.map((c) => {
                if (c.key !== key) return c;
                const next = { ...c, ...patch };
                const effectivePrice = patch.unit_price !== undefined ? patch.unit_price : c.unit_price;
                next.likely_loss = c.average_cost > 0 && effectivePrice < c.average_cost;
                return next;
            }),
        );
    };

    const removeItem = (key: number) => setCart((prev) => prev.filter((c) => c.key !== key));

    const totals = useMemo(() => {
        const subtotal = cart.reduce((sum, c) => sum + c.unit_price * c.quantity - c.discount, 0);
        const couponDiscount = couponApplied?.discount ?? 0;
        const reward = rewardOptions.find((r) => r.id === rewardId);
        const rewardDiscount = reward
            ? reward.type === 'percentage'
                ? (subtotal - couponDiscount) * (Number(reward.value) / 100)
                : Number(reward.value)
            : 0;
        const total = Math.max(subtotal - couponDiscount - rewardDiscount, 0);
        const cashPaid = payments.reduce(
            (sum, p) => (p.method === 'cash' ? sum + (parseFloat(p.amount) || 0) : 0),
            0,
        );
        const nonCashPaid = payments.reduce(
            (sum, p) => (p.method !== 'cash' ? sum + (parseFloat(p.amount) || 0) : 0),
            0,
        );
        const allPaid = payments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
        const required = Math.max(total - nonCashPaid, 0);
        const change = cashPaid > required ? cashPaid - required : 0;
        const amountDiff = Math.round((allPaid - total) * 100) / 100;
        return { subtotal, couponDiscount, rewardDiscount, total, cashPaid, nonCashPaid, allPaid, required, change, amountDiff };
    }, [cart, couponApplied, payments, rewardOptions, rewardId]);

    const applyCoupon = () => {
        if (!couponCode.trim()) return;
        setCouponBusy(true);
        (async () => {
            try {
                const incoming = await api<{ valid: boolean; code: string; discount: number; type: string; value: number } | { valid: false }>(
                    '/coupons/validate',
                    'POST',
                    { code: couponCode, subtotal: totals.subtotal },
                );
                if ('discount' in incoming) {
                    setCouponApplied({ code: incoming.code, discount: Number(incoming.discount) });
                    toast.success(`Coupon ${incoming.code} applied`);
                } else {
                    setCouponApplied(null);
                    toast.error('Invalid or inapplicable coupon');
                }
            } catch {
                setCouponApplied(null);
                toast.error('Coupon validation failed');
            } finally {
                setCouponBusy(false);
            }
        })();
    };

    const clearCoupon = () => {
        setCouponApplied(null);
        setCouponCode('');
    };

    const layawayEnabled = can.layaway && !!customerName.trim() && totals.total > 0;

    const handleCollect = () => {
        const items = cart.map((c) => ({
            product_id: c.product_id,
            product_variant_id: c.variant_id,
            quantity: c.quantity,
            price: Math.abs(c.unit_price - c.catalog_price) > 0.005 ? c.unit_price : undefined,
            price_override_reason: Math.abs(c.unit_price - c.catalog_price) > 0.005 ? c.override_reason || undefined : undefined,
            discount: c.discount > 0 ? c.discount : undefined,
            loss_reason: c.likely_loss
                ? c.loss_reason ||
                  (reductionReason === 'loss'
                      ? 'Sold at a loss (long-held stock / spoilage risk)'
                      : undefined)
                : undefined,
        }));

        const payRows = payments
            .map((p) => ({
                method: p.method,
                amount: p.amount ? parseFloat(p.amount) : 0,
                reference: p.reference || undefined,
            }))
            .filter((p) => p.amount > 0);

        const overStock = cart.find((c) => c.stock_qty >= 0 && c.quantity > c.stock_qty);
        if (overStock) {
            toast.error(t('pos.stock.insufficient', { qty: Math.round(overStock.stock_qty), name: overStock.name }));
            setPayDialogOpen(false);
            return;
        }

        const payload: Record<string, any> = {
            items,
            payments: payRows,
            coupon_code: couponApplied?.code ?? undefined,
        };
        if (customerId) payload.customer_id = customerId;
        if (rewardId) payload.reward_id = rewardId;
        if (layawayMode && layawayEnabled) payload.layaway_deposit = parseFloat(layawayDeposit) || 0;

        if (payRows.length === 0) {
            toast.error(t('pos.confirm.payments_required'));
            return;
        }

        if (layawayMode) {
            const required = parseFloat(layawayDeposit) || 0;
            if (totals.allPaid < required) {
                toast.error(t('pos.confirm.cash_short'));
                return;
            }
        } else {
            const payMismatch = Math.abs(totals.amountDiff) > 0.005;
            if (payMismatch && !reductionReason) {
                toast.error(totals.amountDiff < 0 ? t('pos.confirm.cash_short') : t('pos.reduction.required'));
                return;
            }
        }

        const unresolvedLoss = cart.find((c) => c.likely_loss && !c.loss_reason.trim());
        if (unresolvedLoss) {
            toast.error(`${t('pos.loss.reason_required')}: ${unresolvedLoss.name}`);
            return;
        }

        if (!layawayMode && Math.abs(totals.amountDiff) > 0.005) {
            payload.price_reduction_reason = reductionReason;
            payload.price_reduction_amount = Math.abs(totals.amountDiff);
        }

        setSubmitting(true);
        router.post(sales.store().url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                setSubmitting(false);
                setCart([]);
                setCustomerName('');
                setCustomerId(null);
                setCustomerDetails(null);
                setCouponApplied(null);
                setCouponCode('');
                setRewardId(null);
                setPayments([{ key: nextKey(), method: 'cash', amount: '', reference: '' }]);
                setLayawayDeposit('');
                setLayawayMode(false);
                setReductionReason('');
                setPayDialogOpen(false);
                router.reload({ only: ['active_shift'] });
            },
            onError: (errors) => {
                setSubmitting(false);
                const messages = Object.values(errors ?? {});
                if (messages && messages.length > 0) {
                    toast.error(String(messages[0]));
                } else {
                    toast.error(t('pos.sale.failed'));
                }
            },
        });
    };

    const parkSale = () => {
        const itemPayload = cart.map((c) => ({
            product_id: c.product_id,
            product_variant_id: c.variant_id,
            name: c.name,
            variant_name: c.variant_name,
            price: Math.abs(c.unit_price - c.catalog_price) > 0.005 ? c.unit_price : c.catalog_price,
            discount: c.discount,
            quantity: c.quantity,
        }));
        if (itemPayload.length === 0) return;

        router.post(
            parkedSales.store().url,
            {
                cart: { items: itemPayload },
                customer_id: customerId ?? undefined,
                reference: customerName || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Sale parked');
                    setCart([]);
                    setCustomerName('');
                    setCustomerId(null);
                    setCouponApplied(null);
                    setCouponCode('');
                },
            },
        );
    };

    const replacePaymentMethod = (key: number, method: PaymentRow['method']) => {
        setPayments((prev) => prev.map((p) => (p.key === key ? { ...p, method } : p)));
    };

    const updatePayment = (key: number, patch: Partial<PaymentRow>) => {
        setPayments((prev) => prev.map((p) => (p.key === key ? { ...p, ...patch } : p)));
    };

    const addPaymentRow = () =>
        setPayments((prev) => [
            ...prev,
            { key: nextKey(), method: 'cash', amount: '', reference: '' },
        ]);

    const removePaymentRow = (key: number) =>
        setPayments((prev) => (prev.length > 1 ? prev.filter((p) => p.key !== key) : prev));

    const canCheckout = cart.length > 0 && !submitting;

    return (
        <>
            <Head title="POS / Checkout" />
            <div className="flex flex-col gap-3 p-3 md:p-4">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-2">
                    <Heading
                        title="POS / Checkout"
                        description={`${branch.name} · ${branch.currency}`}
                    />
                    <div className="flex items-center gap-2">
                        {active_shift ? (
                            <Badge variant="outline" className="text-xs py-0.5">
                                Shift #{active_shift.shift_no} · Float {formatMoney(Number(active_shift.opening_float))}
                            </Badge>
                        ) : (
                            <Badge variant="destructive" className="text-xs py-0.5">No open shift</Badge>
                        )}
                    </div>
                </div>

                {!active_shift && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs text-amber-800 dark:border-amber-700/50 dark:bg-amber-950/40 dark:text-amber-200">
                        No shift is open for this branch. Open a shift before taking payments so the drawer reconciles correctly.
                    </div>
                )}

                <div className="grid gap-3 lg:grid-cols-12">
                    {/* Left Column: Search & Cart Table (8 cols) */}
                    <div className="flex flex-col gap-3 lg:col-span-8">
                        <div className="relative">
                            <ScanBarcode className="text-muted-foreground absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2" />
                            <Input
                                ref={scannerRef}
                                id="scan"
                                placeholder="Scan barcode or search product... (press /)"
                                className="pl-8 h-9 text-xs"
                                value={query}
                                autoFocus
                                onChange={(e) => setQuery(e.target.value)}
                            />
                            {results.length > 0 && (
                                <div className="absolute z-20 mt-1 w-full rounded-md border bg-popover shadow-md max-h-60 overflow-y-auto">
                                    {results.map((p) => (
                                        <button
                                            key={p.id}
                                            type="button"
                                            onClick={() => handleScan(p)}
                                            className="hover:bg-accent hover:text-accent-foreground flex w-full items-center justify-between border-b px-3 py-1.5 text-left text-xs last:border-0"
                                        >
                                            <div className="min-w-0">
                                                <div className="truncate font-medium">{p.name}</div>
                                                <div className="text-muted-foreground truncate text-[11px]">
                                                    {p.sku} {p.barcode ? `· ${p.barcode}` : ''} {p.stock_qty >= 0 ? `· Stk: ${Math.round(p.stock_qty)}` : ''}
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="font-mono font-semibold">{formatMoney(p.shop_price)}</div>
                                                {p.variants.length > 1 && (
                                                    <div className="text-muted-foreground text-[10px]">{p.variants.length} variants</div>
                                                )}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Cart */}
                        <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
                            <div className="flex items-center justify-between border-b px-3 py-2">
                                <div className="flex items-center gap-1.5 text-xs font-semibold">
                                    <ShoppingCart className="h-3.5 w-3.5" />
                                    Cart Items
                                </div>
                                <Badge variant="secondary" className="text-[10px] h-5 px-1.5">{cart.length} line{cart.length === 1 ? '' : 's'}</Badge>
                            </div>

                            {cart.length === 0 ? (
                                <div className="text-muted-foreground flex h-48 flex-col items-center justify-center gap-1 text-xs">
                                    <Search className="h-5 w-5 opacity-40" />
                                    Scan or search a product to add it to the cart.
                                </div>
                            ) : (
                                <div className="divide-y text-xs">
                                    <div className="grid grid-cols-12 bg-muted/40 px-3 py-1.5 text-[11px] font-medium text-muted-foreground">
                                        <div className="col-span-5">Product</div>
                                        <div className="col-span-2 text-center">Qty</div>
                                        <div className="col-span-2 text-right">Price</div>
                                        <div className="col-span-2 text-right">Disc.</div>
                                        <div className="col-span-1 text-center">Action</div>
                                    </div>

                                    {cart.map((item) => (
                                        <div key={item.key} className="p-2 px-3 hover:bg-muted/30">
                                            <div className="grid grid-cols-12 items-center gap-1.5">
                                                <div className="col-span-5 min-w-0">
                                                    <div className="truncate font-medium text-xs">{item.name}</div>
                                                    <div className="text-muted-foreground text-[10px] flex items-center gap-1">
                                                        <span>{item.variant_name ?? 'Standard'}</span>
                                                        {item.average_cost > 0 && (
                                                            <span className="text-amber-600">· cost {formatMoney(item.average_cost)}</span>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="col-span-2 flex justify-center">
                                                    <div className="flex items-center gap-0.5">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            className="h-6 w-6"
                                                            onClick={() => updateItem(item.key, { quantity: Math.max(0.5, item.quantity - 1) })}
                                                        >
                                                            <Minus className="h-2.5 w-2.5" />
                                                        </Button>
                                                        <Input
                                                            type="number"
                                                            min="0.5"
                                                            step="0.5"
                                                            className="h-6 w-12 px-1 text-center text-xs font-mono"
                                                            value={item.quantity}
                                                            onChange={(e) => updateItem(item.key, { quantity: parseFloat(e.target.value) || 1 })}
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            className="h-6 w-6"
                                                            onClick={() => updateItem(item.key, { quantity: item.quantity + 1 })}
                                                        >
                                                            <Plus className="h-2.5 w-2.5" />
                                                        </Button>
                                                    </div>
                                                </div>

                                                <div className="col-span-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className="h-6 px-1 text-right font-mono text-xs"
                                                        value={item.unit_price}
                                                        onChange={(e) => updateItem(item.key, { unit_price: parseFloat(e.target.value) || 0 })}
                                                    />
                                                </div>

                                                <div className="col-span-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className="h-6 px-1 text-right font-mono text-xs"
                                                        value={item.discount}
                                                        onChange={(e) => updateItem(item.key, { discount: parseFloat(e.target.value) || 0 })}
                                                    />
                                                </div>

                                                <div className="col-span-1 text-center">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-6 w-6 text-destructive hover:text-destructive"
                                                        onClick={() => removeItem(item.key)}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </div>

                                            {item.unit_price > item.catalog_price + 0.005 && (
                                                <div className="mt-1 flex items-center gap-2 pl-1">
                                                    <Input
                                                        className="h-6 text-[11px] flex-1"
                                                        placeholder="Price override reason (required)"
                                                        value={item.override_reason}
                                                        onChange={(e) => updateItem(item.key, { override_reason: e.target.value })}
                                                    />
                                                </div>
                                            )}

                                            {item.likely_loss && (
                                                <div className="mt-1 flex items-center gap-2 pl-1">
                                                    <Label className="text-destructive text-[10px] font-medium whitespace-nowrap">Selling below cost</Label>
                                                    <Input
                                                        className="h-6 text-[11px] flex-1"
                                                        placeholder="Loss reason (required)"
                                                        value={item.loss_reason}
                                                        onChange={(e) => updateItem(item.key, { loss_reason: e.target.value })}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right Column: Customer, Coupon, Payment & Summary (4 cols) */}
                    <div className="flex flex-col gap-3 lg:col-span-4">
                        {/* Customer & Coupon Card */}
                        <div className="rounded-lg border bg-card p-3 shadow-sm flex flex-col gap-2.5">
                            <div className="grid gap-1">
                                <Label htmlFor="customer_search" className="text-xs flex items-center gap-1">
                                    <UserCheck className="h-3 w-3" /> Customer
                                </Label>
                                <Input
                                    id="customer_search"
                                    placeholder="Search name / phone..."
                                    className="h-8 text-xs"
                                    value={customerSearch}
                                    onChange={(e) => {
                                        setCustomerSearch(e.target.value);
                                        setCustomerName(e.target.value);
                                        setCustomerId(null);
                                        setCustomerDetails(null);
                                        setRewardId(null);
                                        setPayments((prev) =>
                                            prev.map((p) =>
                                                p.method === 'credit' || p.method === 'deposit'
                                                    ? { ...p, method: 'cash', amount: '' }
                                                    : p,
                                            ),
                                        );
                                    }}
                                />
                                {customerSearch.trim() && customerResults.length > 0 && (
                                    <div className="rounded-md border bg-popover shadow-sm max-h-36 overflow-y-auto mt-1">
                                        {customerResults.map((c) => (
                                            <button
                                                key={c.id}
                                                type="button"
                                                onClick={() => {
                                                    setCustomerSearch(`${c.name}${c.phone ? ` · ${c.phone}` : ''}`);
                                                    setCustomerDetails(c);
                                                    setCustomerName(c.name);
                                                    setCustomerId(c.id);
                                                    setCustomerResults([]);
                                                }}
                                                className="hover:bg-accent flex w-full items-center justify-between gap-2 px-2.5 py-1 text-left text-xs border-b last:border-0"
                                            >
                                                <span className="truncate">
                                                    <span className="font-medium block">{c.name}</span>
                                                    <span className="text-muted-foreground text-[10px]">{c.customer_no}</span>
                                                </span>
                                                <span className="text-muted-foreground text-[10px] whitespace-nowrap">{c.phone ?? ''}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                {customerDetails && (
                                    <div className="text-muted-foreground flex flex-wrap gap-x-3 gap-y-0.5 text-[10px]">
                                        <Badge variant="secondary" className="text-[10px] px-1.5 h-4">
                                            {customerDetails.customer_type}
                                        </Badge>
                                        <span>
                                            {customerDetails.loyalty_points} pts · {customerDetails.loyalty_tier}
                                        </span>
                                        <span>Deposit {formatMoney(Number(customerDetails.deposit_balance))}</span>
                                        <span>Credit {formatMoney(Number(customerDetails.credit_balance))}</span>
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="coupon" className="text-xs flex items-center gap-1">
                                    <Tag className="h-3 w-3" /> Coupon
                                </Label>
                                <div className="flex gap-1.5">
                                    <Input
                                        id="coupon"
                                        placeholder="Code"
                                        className="h-8 text-xs"
                                        value={couponCode}
                                        disabled={!!couponApplied}
                                        onChange={(e) => setCouponCode(e.target.value)}
                                    />
                                    {couponApplied ? (
                                        <Button type="button" variant="outline" size="sm" className="h-8 px-2" onClick={clearCoupon}>
                                            <X className="h-3.5 w-3.5" />
                                        </Button>
                                    ) : (
                                        <Button type="button" variant="outline" size="sm" className="h-8 px-2.5 text-xs" onClick={applyCoupon} disabled={couponBusy}>
                                            Apply
                                        </Button>
                                    )}
                                </div>
                                {couponApplied && (
                                    <div className="text-emerald-600 text-[10px] font-medium">
                                        {couponApplied.code} — {formatMoney(couponApplied.discount)} off
                                    </div>
                                )}
                            </div>

                            {rewardOptions.length > 0 && (
                                <div className="grid gap-1">
                                    <Label htmlFor="reward" className="text-xs flex items-center gap-1">
                                        <Gift className="h-3 w-3" /> Loyalty reward
                                    </Label>
                                    <Select value={rewardId ? String(rewardId) : ''} onValueChange={(v) => setRewardId(v ? Number(v) : null)}>
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue placeholder="None" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {rewardOptions.map((r) => (
                                                <SelectItem
                                                    key={r.id}
                                                    value={String(r.id)}
                                                    className="text-xs"
                                                    disabled={customerDetails ? customerDetails.loyalty_points < r.points_cost : false}
                                                >
                                                    {r.name} · {r.points_cost} pts
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {can.layaway && (
                                <div className="flex items-center gap-2 pt-1 border-t mt-1">
                                    <input
                                        id="layaway-toggle"
                                        type="checkbox"
                                        checked={layawayMode}
                                        onChange={(e) => setLayawayMode(e.target.checked)}
                                        className="h-3.5 w-3.5 rounded border-input"
                                    />
                                    <Label htmlFor="layaway-toggle" className="text-xs cursor-pointer">
                                        Enable Layaway (Deposit)
                                    </Label>
                                </div>
                            )}

                            {layawayMode && (
                                <div className="grid gap-1 mt-1">
                                    <Input
                                        id="layaway_deposit"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Deposit amount"
                                        className="h-8 text-xs font-mono"
                                        value={layawayDeposit}
                                        onChange={(e) => setLayawayDeposit(e.target.value)}
                                    />
                                </div>
                            )}
                        </div>

                        {/* Payment Breakdown using shadcn UI Select Component */}
                        <div className="rounded-lg border bg-card p-3 shadow-sm flex flex-col gap-2">
                            <Label className="text-xs font-semibold flex items-center gap-1">
                                <CreditCard className="h-3 w-3" /> Payments
                            </Label>
                            <div className="flex flex-col gap-1.5">
                                {payments.map((p) => (
                                    <div key={p.key} className="flex flex-col gap-1 border-b pb-1.5 last:border-0 last:pb-0">
                                        <div className="flex items-center gap-1.5">
                                            <Select
                                                value={p.method}
                                                onValueChange={(val) => replacePaymentMethod(p.key, val as PaymentRow['method'])}
                                            >
                                                <SelectTrigger className="h-8 flex-1 text-xs">
                                                    <SelectValue placeholder="Method" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {PAYMENT_METHODS.map((m) => (
                                                        <SelectItem key={m.value} value={m.value} className="text-xs">
                                                            {m.label}
                                                        </SelectItem>
                                                    ))}
                                                    {customerDetails && Number(customerDetails.deposit_balance) > 0 && (
                                                        <SelectItem value="deposit" className="text-xs">
                                                            Deposit
                                                        </SelectItem>
                                                    )}
                                                    {customerDetails?.customer_type === 'credit' && (
                                                        <SelectItem value="credit" className="text-xs">
                                                            Credit
                                                        </SelectItem>
                                                    )}
                                                </SelectContent>
                                            </Select>

                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className="h-8 w-24 text-right font-mono text-xs"
                                                placeholder="0.00"
                                                value={p.amount}
                                                onChange={(e) => updatePayment(p.key, { amount: e.target.value })}
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8 text-destructive"
                                                onClick={() => removePaymentRow(p.key)}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                        {(p.method === 'card' || p.method === 'mobile' || p.method === 'bank_transfer') && (
                                            <Input
                                                className="h-7 text-[11px]"
                                                placeholder={p.method === 'mobile' ? 'TXN / Phone Ref' : 'Reference'}
                                                value={p.reference}
                                                onChange={(e) => updatePayment(p.key, { reference: e.target.value })}
                                            />
                                        )}
                                    </div>
                                ))}
                                <Button type="button" variant="outline" size="sm" className="h-7 text-xs mt-0.5" onClick={addPaymentRow}>
                                    <Plus className="mr-1 h-3 w-3" /> Add Payment
                                </Button>
                            </div>
                        </div>

                        {/* Order Summary */}
                        <div className="rounded-lg border bg-card p-3 shadow-sm flex flex-col gap-2">
                            <div className="flex flex-col gap-1 text-xs">
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Subtotal</span>
                                    <span className="font-mono">{formatMoney(totals.subtotal)}</span>
                                </div>
                                {totals.couponDiscount > 0 && (
                                    <div className="flex justify-between text-emerald-600">
                                        <span>Coupon</span>
                                        <span className="font-mono">−{formatMoney(totals.couponDiscount)}</span>
                                    </div>
                                )}
                                {totals.rewardDiscount > 0 && (
                                    <div className="flex justify-between text-emerald-600">
                                        <span>Reward</span>
                                        <span className="font-mono">−{formatMoney(totals.rewardDiscount)}</span>
                                    </div>
                                )}
                                {totals.cashPaid > 0 && (
                                    <div className="flex justify-between text-muted-foreground">
                                        <span>Cash Received</span>
                                        <span className="font-mono">{formatMoney(totals.cashPaid)}</span>
                                    </div>
                                )}
                                {totals.change > 0 && (
                                    <div className="flex justify-between text-blue-600 font-medium">
                                        <span>Change Due</span>
                                        <span className="font-mono">{formatMoney(totals.change)}</span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between border-t pt-1.5 mt-1 text-sm font-bold">
                                    <span>{layawayMode ? 'Required Deposit' : 'Total'}</span>
                                    <span className="font-mono text-base">
                                        {formatMoney(layawayMode ? parseFloat(layawayDeposit) || 0 : totals.total)}
                                    </span>
                                </div>
                            </div>

                            <div className="grid gap-1.5 pt-1">
                                <Button
                                    type="button"
                                    className="h-10 w-full text-xs font-semibold"
                                    disabled={!canCheckout}
                                    onClick={() => {
                                        if (layawayMode) {
                                            handleCollect();
                                        } else {
                                            setPayDialogOpen(true);
                                        }
                                    }}
                                >
                                    Charge {formatMoney(totals.total)}
                                </Button>
                                {can.park && cart.length > 0 && (
                                    <Button type="button" variant="outline" className="h-8 text-xs" onClick={parkSale}>
                                        <PauseCircle className="mr-1 h-3.5 w-3.5" /> Park Sale
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Dialogs */}
                <Dialog open={payDialogOpen} onOpenChange={setPayDialogOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="text-base">Confirm Sale</DialogTitle>
                            <DialogDescription className="text-xs">
                                {cart.length} item(s) · Subtotal {formatMoney(totals.subtotal)}
                                {totals.couponDiscount > 0 && <> · Coupon −{formatMoney(totals.couponDiscount)}</>}
                                {' '}· Total {formatMoney(totals.total)}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-1.5 text-xs">
                            <div className="font-medium text-muted-foreground">Payment Breakdown:</div>
                            {payments.map((p) => (
                                <div key={p.key} className="flex justify-between rounded bg-muted px-2.5 py-1">
                                    <span className="capitalize">{p.method.replace('_', ' ')}</span>
                                    <span className="font-mono">{formatMoney(parseFloat(p.amount) || 0)}</span>
                                </div>
                            ))}
                            {totals.change > 0 && (
                                <div className="flex justify-between rounded bg-blue-50 px-2.5 py-1 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200 font-medium">
                                    <span>Change Due (Cash)</span>
                                    <span className="font-mono">{formatMoney(totals.change)}</span>
                                </div>
                            )}
                            {totals.allPaid < totals.total && (
                                <div className="flex justify-between rounded bg-amber-50 px-2.5 py-1 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200 font-medium">
                                    <span>Remaining (Unpaid)</span>
                                    <span className="font-mono">{formatMoney(totals.total - totals.allPaid)}</span>
                                </div>
                            )}
                        </div>

                        {!layawayMode && Math.abs(totals.amountDiff) > 0.005 && (
                            <div className="rounded-md border border-amber-300 bg-amber-50 p-2.5 dark:border-amber-900 dark:bg-amber-950/40">
                                <div className="flex items-center justify-between text-xs font-semibold text-amber-800 dark:text-amber-200">
                                    <span>{t('pos.reduction.title')}</span>
                                    <span className={'font-mono ' + (totals.amountDiff < 0 ? '' : 'text-emerald-700 dark:text-emerald-300')}>
                                        {totals.amountDiff < 0 ? `−${formatMoney(Math.abs(totals.amountDiff))}` : `+${formatMoney(totals.amountDiff)}`}
                                    </span>
                                </div>
                                <p className="mt-0.5 text-[11px] text-amber-700 dark:text-amber-300">{t('pos.reduction.desc')}</p>
                                <div className="mt-2 grid gap-1.5">
                                    {[
                                        { value: 'discount' as const, label: t('pos.reduction.discount_label'), hint: t('pos.reduction.discount_hint') },
                                        { value: 'loss' as const, label: t('pos.reduction.loss_label'), hint: t('pos.reduction.loss_hint') },
                                        { value: 'overpayment' as const, label: t('pos.reduction.overpayment_label'), hint: t('pos.reduction.overpayment_hint') },
                                    ].map((opt) => (
                                        <button
                                            key={opt.value}
                                            type="button"
                                            onClick={() => setReductionReason(opt.value)}
                                            className={`flex items-start gap-2 rounded-md border px-2.5 py-2 text-left text-xs transition ${
                                                reductionReason === opt.value
                                                    ? 'border-amber-600 bg-amber-100 dark:border-amber-500 dark:bg-amber-900/60'
                                                    : 'border-border bg-background hover:bg-accent'
                                            }`}
                                        >
                                            <span
                                                className={`mt-0.5 flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border ${
                                                    reductionReason === opt.value
                                                        ? 'border-amber-600 dark:border-amber-400'
                                                        : 'border-muted-foreground'
                                                }`}
                                            >
                                                {reductionReason === opt.value && (
                                                    <span className="h-1.5 w-1.5 rounded-full bg-amber-600 dark:bg-amber-400" />
                                                )}
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block font-medium">{opt.label}</span>
                                                <span className="text-muted-foreground block text-[10px]">{opt.hint}</span>
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                {!reductionReason && (
                                    <p className="mt-1.5 text-[10px] font-medium text-destructive">{t('pos.reduction.required')}</p>
                                )}
                            </div>
                        )}

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" className="h-8 text-xs" onClick={() => setPayDialogOpen(false)} disabled={submitting}>
                                Back
                            </Button>
                            <Button
                                className="h-8 text-xs"
                                onClick={handleCollect}
                                disabled={submitting || (!layawayMode && Math.abs(totals.amountDiff) > 0.005 && !reductionReason)}
                            >
                                {submitting ? 'Processing…' : 'Complete Sale'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={!!variantChoice} onOpenChange={(open) => !open && setVariantChoice(null)}>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle className="text-base">Choose Variant</DialogTitle>
                            <DialogDescription className="text-xs">{variantChoice?.name}</DialogDescription>
                        </DialogHeader>
                        <div className="flex max-h-60 flex-col gap-1.5 overflow-y-auto">
                            {variantChoice?.variants.map((v) => (
                                <button
                                    key={v.id}
                                    type="button"
                                    onClick={() => {
                                        addToCart(variantChoice, v.id);
                                        setVariantChoice(null);
                                    }}
                                    className="hover:bg-accent flex items-center justify-between rounded-md border px-2.5 py-1.5 text-left text-xs"
                                >
                                    <span>
                                        <span className="block font-medium">{v.name}</span>
                                        <span className="text-muted-foreground text-[10px]">{v.sku}</span>
                                    </span>
                                    <span className="font-mono font-medium">{formatMoney(v.price)}</span>
                                </button>
                            ))}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" className="h-8 text-xs" onClick={() => setVariantChoice(null)}>
                                Cancel
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

Pos.layout = {
    breadcrumbs: [{ title: 'POS / Checkout', href: '/sales/pos' }],
};