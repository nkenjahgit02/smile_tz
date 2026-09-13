import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    Activity,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    Calendar,
    ChevronRight,
    Clock,
    DollarSign,
    Package,
    RefreshCw,
    ShoppingBag,
    ShoppingCart,
    TicketPercent,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ComposedChart,
    Line,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import orders from '@/routes/orders';
import purchaseOrders from '@/routes/purchase-orders';
import sales from '@/routes/sales';
import stock from '@/routes/stock';
import { formatMoney, formatNumber } from '@/lib/format';
import i18n from '@/i18n';
import { useTranslation } from 'react-i18next';

type Kpi = { value: number; delta: number | null };

type Kpis = {
    revenue: Kpi;
    transactions: Kpi;
    items_sold: Kpi;
    avg_basket: Kpi;
};

type Filter = {
    from: string;
    to: string;
    period_key: string;
    available_years: number[];
};

type GrossProfit = { amount: number; margin: number };

type PaymentSplit = {
    method: string;
    label: string;
    color: string;
    amount: number;
    percentage: number;
};

type TrendPoint = { label: string; revenue: number; transactions: number };
type CategoryRow = { category: string; revenue: number; qty: number };
type HourPoint = { label: string; hour: number; revenue: number };
type WeekdayRow = { label: string; revenue: number; transactions: number };
type BranchRow = {
    id: number;
    name: string;
    revenue: number;
    transactions: number;
    share: number;
};

type RecentSale = {
    id: number;
    sale_no: string;
    status: string;
    payment_status: string;
    total: number;
    amount_paid: number;
    created_at: string;
    branch: string | null;
    user: string | null;
    customer: string | null;
};

type TopProduct = { name: string; sku: string; qty: number; revenue: number };

type Location = { id: number; name: string; type: 'godown' | 'store' };

type ProductAlert = {
    id: number;
    name: string;
    sku: string;
    quantity: number;
    reorder_level: number;
    location_id: number;
    location_name: string;
    location_type: 'godown' | 'store';
};

type CategoryAlert = {
    category: string;
    location_id: number;
    location_name: string;
    location_type: 'godown' | 'store';
    quantity: number;
    threshold: number;
    percent_of_normal: number;
};

type GodownAlert = {
    godown_id: number;
    godown_name: string;
    product_name: string;
    sku: string;
    quantity: number;
    reorder_level: number;
};

type Alerts = {
    product_alerts: ProductAlert[];
    category_alerts: CategoryAlert[];
    godown_alerts: GodownAlert[];
    out_of_stock: number;
    parked: number;
    open_shifts: number;
    active_layaways: number;
    pending_stock_requests: number;
};

type CustomerHighlights = {
    credit_outstanding: number;
    deposits_held: number;
    new_customers: number;
    returning_customers: number;
    walk_in_orders: number;
    active_layaways: number;
    layaway_balance: number;
    top_customers: {
        id: number;
        name: string;
        customer_type: string;
        loyalty_tier: string;
        loyalty_points: number;
        lifetime_spend: number;
    }[];
};

type ActivityRow = {
    id: number;
    description: string;
    event: string | null;
    user: string;
    created_at: string | null;
};

type FinancePositions = {
    pending_refunds: number;
    voids_24h: number;
    flagged_voids_24h: number;
    cash_variances_today: number;
    pending_cash_outs: number;
    receivables: number;
    payables: number;
};

type Props = {
    selected_branch_id: number | null;
    filter: Filter;
    kpis: Kpis;
    gross_profit: GrossProfit;
    refunds: { count: number; amount: number };
    promotions: { coupons_used: number; discount: number };
    payment_split: PaymentSplit[];
    trend: TrendPoint[];
    categories: CategoryRow[];
    hourly: HourPoint[];
    weekdays: WeekdayRow[];
    branches: BranchRow[];
    recent_sales: RecentSale[];
    top_products: TopProduct[];
    alerts: Alerts | null;
    locations: Location[];
    customers: CustomerHighlights | null;
    activity: ActivityRow[];
    finance: FinancePositions | null;
    order_pipeline: Record<string, number>;
    stock_value: {
        total: number;
        by_location: {
            id: number;
            name: string;
            type: string;
            value: number;
        }[];
    } | null;
    global_filters: { product?: string; status?: string; branch?: string };
    products: { id: number; name: string }[];
    can: {
        view_financials: boolean;
        view_stock_alerts: boolean;
        view_stock_tier2_tier3: boolean;
        view_cross_location: boolean;
        view_customers: boolean;
        view_activity: boolean;
        own_sales_only: boolean;
        sales: boolean;
        stock: boolean;
        customers: boolean;
        activity: boolean;
        restock: boolean;
        orders: boolean;
    };
};

// Consistent semantic palette used across every chart on the page, so a
// colour always means the same metric no matter which card it appears in.
const CHART = {
    revenue: '#4f46e5', // indigo
    transactions: '#0d9488', // teal
    categories: '#d97706', // amber
    hourly: '#0891b2', // cyan
    hourlyMuted: '#0891b233',
    weekday: '#7c3aed', // violet
};

const compact = (value: number) =>
    Intl.NumberFormat(i18n.resolvedLanguage === 'sw' ? 'sw-TZ' : 'en-US', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value || 0);

const timeOf = (value: string | null) =>
    value
        ? new Date(value).toLocaleTimeString('en-GB', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

const isoDate = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

const daysAgo = (days: number) => {
    const date = new Date();
    date.setDate(date.getDate() - days);
    return date;
};

const paymentStatusTone = (status: string) => {
    const s = status.toLowerCase();
    if (s.includes('paid') || s.includes('lipwa'))
        return 'border-emerald-500/40 text-emerald-700 dark:text-emerald-400';
    if (s.includes('partial') || s.includes('sehemu'))
        return 'border-amber-500/40 text-amber-700 dark:text-amber-400';
    if (s.includes('refund') || s.includes('rejesh'))
        return 'border-rose-500/40 text-rose-700 dark:text-rose-400';
    return 'border-border text-muted-foreground';
};

const locationLabel = (location: { name: string; type: 'godown' | 'store' }) =>
    `${location.name} (${location.type === 'godown' ? 'Godown' : 'Store'})`;

function DeltaTag({ delta }: { delta: number | null }) {
    if (delta === null)
        return <span className="text-muted-foreground text-[10px]">—</span>;
    if (delta === 0)
        return <span className="text-muted-foreground text-[10px]">0%</span>;
    const isPositive = delta > 0;

    return (
        <span
            className={`inline-flex items-center gap-0.5 text-[10px] font-semibold tabular-nums ${
                isPositive
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-rose-600 dark:text-rose-400'
            }`}
        >
            {isPositive ? (
                <ArrowUpRight className="size-3" />
            ) : (
                <ArrowDownRight className="size-3" />
            )}
            {Math.abs(delta)}%
        </span>
    );
}

function CompactKpiCard({
    label,
    value,
    delta,
    format,
    icon: Icon,
}: {
    label: string;
    value: number;
    delta: number | null;
    format: (value: number) => string;
    icon: React.ElementType;
}) {
    return (
        <Card className="border-border/60 h-full shadow-none">
            <CardContent className="flex h-full flex-col justify-between gap-2 p-3">
                <div className="text-muted-foreground flex items-center justify-between">
                    <span className="text-xs font-medium">{label}</span>
                    <Icon className="text-muted-foreground/70 size-3.5 shrink-0" />
                </div>
                <div className="flex items-baseline justify-between gap-1">
                    <span className="text-lg font-semibold tracking-tight tabular-nums">
                        {format(value)}
                    </span>
                    <DeltaTag delta={delta} />
                </div>
            </CardContent>
        </Card>
    );
}

function FinanceStat({
    icon: Icon,
    label,
    value,
    sub,
    tone,
}: {
    icon: React.ElementType;
    label: string;
    value: string;
    sub?: string;
    tone: 'rose' | 'amber' | 'blue';
}) {
    const toneClass =
        tone === 'rose'
            ? 'text-rose-600 dark:text-rose-400'
            : tone === 'amber'
              ? 'text-amber-600 dark:text-amber-400'
              : 'text-blue-600 dark:text-blue-400';

    return (
        <div className="border-border/60 flex items-start gap-2.5 border-b p-3 last:border-none sm:border-r sm:border-b-0 sm:last:border-none">
            <div
                className={`mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-current/10 ${toneClass}`}
            >
                <Icon className="size-3.5" />
            </div>
            <div className="min-w-0">
                <p className="text-muted-foreground text-xs">{label}</p>
                <p className="text-sm font-semibold tabular-nums">{value}</p>
                {sub && (
                    <p className="text-muted-foreground text-[11px]">{sub}</p>
                )}
            </div>
        </div>
    );
}

const CustomTooltip = ({ active, payload, label, formatter }: any) => {
    if (active && payload && payload.length) {
        return (
            <div className="bg-popover rounded-md border p-2 text-xs shadow-md">
                <p className="text-muted-foreground font-medium">{label}</p>
                {payload.map((entry: any, index: number) => (
                    <div
                        key={index}
                        className="text-popover-foreground mt-1 flex items-center gap-2 font-semibold"
                    >
                        <span
                            className="size-2 rounded-full"
                            style={{ backgroundColor: entry.color }}
                        />
                        <span className="tabular-nums">
                            {formatter ? formatter(entry.value) : entry.value}
                        </span>
                    </div>
                ))}
            </div>
        );
    }
    return null;
};

export default function AdvancedDashboard({
    selected_branch_id,
    filter,
    kpis,
    gross_profit,
    refunds,
    promotions,
    payment_split,
    trend,
    categories,
    hourly,
    weekdays,
    branches,
    recent_sales,
    top_products,
    alerts,
    locations,
    customers,
    activity,
    finance,
    order_pipeline,
    stock_value,
    global_filters,
    products,
    can,
}: Props) {
    const { t } = useTranslation();
    const canSelectBranch = locations?.length > 1;

    const today = new Date();
    const todayIso = isoDate(today);
    const currentHour = today.getHours();

    const [fromInput, setFromInput] = useState(filter.from);
    const [toInput, setToInput] = useState(filter.to);

    const totalPayments = useMemo(
        () => payment_split.reduce((sum, entry) => sum + entry.amount, 0),
        [payment_split],
    );

    const hasAlerts =
        !!alerts &&
        (alerts.product_alerts.length > 0 ||
            alerts.category_alerts.length > 0 ||
            alerts.godown_alerts.length > 0 ||
            alerts.out_of_stock > 0 ||
            alerts.parked > 0 ||
            alerts.open_shifts > 0 ||
            alerts.active_layaways > 0 ||
            alerts.pending_stock_requests > 0);

    const go = (from: string, to: string) =>
        router.get(
            dashboard().url,
            { from, to, branch: selected_branch_id ?? undefined },
            { preserveState: true, preserveScroll: true },
        );

    const switchBranch = (branch: string) =>
        router.get(
            dashboard().url,
            { branch: branch || undefined, from: filter.from, to: filter.to },
            { preserveState: true, preserveScroll: true },
        );

    const presets = [
        { key: 'today', label: t('Today'), from: todayIso, to: todayIso },
        {
            key: '7d',
            label: t('7 days'),
            from: isoDate(daysAgo(6)),
            to: todayIso,
        },
        {
            key: '30d',
            label: t('30 days'),
            from: isoDate(daysAgo(29)),
            to: todayIso,
        },
    ];

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex flex-col gap-3 p-3 md:p-5">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title={t('Business Overview')}
                        description={t('{{from}} to {{to}}', {
                            from: filter.from,
                            to: filter.to,
                        })}
                    />

                    <div className="flex flex-wrap items-center gap-1.5">
                        <div className="bg-card inline-flex rounded-full border p-0.5">
                            {presets.map((p) => (
                                <Button
                                    key={p.key}
                                    size="sm"
                                    variant={
                                        filter.period_key === p.key
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    className="h-6 rounded-full px-2.5 text-[11px] font-medium"
                                    onClick={() => go(p.from, p.to)}
                                >
                                    {p.label}
                                </Button>
                            ))}
                        </div>

                        <div className="bg-card flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[11px]">
                            <Calendar className="text-muted-foreground size-3" />
                            <input
                                type="date"
                                aria-label={t('From date')}
                                value={fromInput}
                                onChange={(e) => setFromInput(e.target.value)}
                                className="bg-transparent tabular-nums focus:outline-none"
                            />
                            <span className="text-muted-foreground">-</span>
                            <input
                                type="date"
                                aria-label={t('To date')}
                                value={toInput}
                                onChange={(e) => setToInput(e.target.value)}
                                className="bg-transparent tabular-nums focus:outline-none"
                            />
                            <Button
                                size="sm"
                                variant="ghost"
                                className="h-5 px-1 text-[11px]"
                                onClick={() => go(fromInput, toInput)}
                            >
                                {t('Apply')}
                            </Button>
                        </div>

                        {canSelectBranch && (
                            <select
                                className="bg-card h-7 rounded-lg border px-2 text-[11px] font-medium focus:outline-none"
                                value={selected_branch_id ?? ''}
                                onChange={(e) => switchBranch(e.target.value)}
                            >
                                <option value="">{t('All Locations')}</option>
                                {locations.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {locationLabel(b)}
                                    </option>
                                ))}
                            </select>
                        )}

                        {products.length > 0 && (
                            <select
                                aria-label={t('Product')}
                                className="bg-card h-7 max-w-40 rounded-lg border px-2 text-[11px] font-medium focus:outline-none"
                                value={global_filters.product ?? ''}
                                onChange={(e) =>
                                    router.get(
                                        dashboard().url,
                                        {
                                            from: filter.from,
                                            to: filter.to,
                                            branch:
                                                selected_branch_id ?? undefined,
                                            product:
                                                e.target.value || undefined,
                                            status:
                                                global_filters.status ??
                                                undefined,
                                        },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                <option value="">{t('All products')}</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        )}

                        <select
                            aria-label={t('Status')}
                            className="bg-card h-7 rounded-lg border px-2 text-[11px] font-medium focus:outline-none"
                            value={global_filters.status ?? ''}
                            onChange={(e) =>
                                router.get(
                                    dashboard().url,
                                    {
                                        from: filter.from,
                                        to: filter.to,
                                        branch: selected_branch_id ?? undefined,
                                        product:
                                            global_filters.product ?? undefined,
                                        status: e.target.value || undefined,
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                )
                            }
                        >
                            <option value="">{t('Active sales')}</option>
                            <option value="active">{t('Active')}</option>
                            <option value="returned">{t('Returned')}</option>
                            <option value="voided">{t('Voided')}</option>
                        </select>
                    </div>
                </div>

                {can.sales && (
                    <>
                        {/* Hero revenue metric + secondary KPIs — one flat grid so every
                            card sits in the same row track and stretches to equal height,
                            regardless of whether a label wraps to one or two lines. */}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                            <Card className="border-border/60 h-full shadow-none sm:col-span-2 lg:col-span-2">
                                <CardContent className="flex h-full flex-col justify-between gap-3 p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="text-muted-foreground flex items-center gap-2">
                                            <div className="flex size-7 items-center justify-center rounded-md bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                                <DollarSign className="size-4" />
                                            </div>
                                            <span className="text-sm font-medium">
                                                {t('Revenue')}
                                            </span>
                                        </div>
                                        <DeltaTag delta={kpis.revenue.delta} />
                                    </div>

                                    <div className="flex items-end justify-between gap-4">
                                        <span className="text-3xl font-bold tracking-tight tabular-nums">
                                            {formatMoney(kpis.revenue.value)}
                                        </span>
                                        <div className="h-12 w-28">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <AreaChart
                                                    data={trend}
                                                    margin={{
                                                        top: 2,
                                                        right: 0,
                                                        left: 0,
                                                        bottom: 0,
                                                    }}
                                                >
                                                    <defs>
                                                        <linearGradient
                                                            id="heroRevenue"
                                                            x1="0"
                                                            y1="0"
                                                            x2="0"
                                                            y2="1"
                                                        >
                                                            <stop
                                                                offset="0%"
                                                                stopColor={
                                                                    CHART.revenue
                                                                }
                                                                stopOpacity={
                                                                    0.35
                                                                }
                                                            />
                                                            <stop
                                                                offset="100%"
                                                                stopColor={
                                                                    CHART.revenue
                                                                }
                                                                stopOpacity={0}
                                                            />
                                                        </linearGradient>
                                                    </defs>
                                                    <Area
                                                        type="monotone"
                                                        dataKey="revenue"
                                                        stroke={CHART.revenue}
                                                        strokeWidth={1.5}
                                                        fill="url(#heroRevenue)"
                                                    />
                                                </AreaChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <CompactKpiCard
                                label={t('Transactions')}
                                value={kpis.transactions.value}
                                delta={kpis.transactions.delta}
                                format={formatNumber}
                                icon={ShoppingCart}
                            />
                            <CompactKpiCard
                                label={t('Items Sold')}
                                value={kpis.items_sold.value}
                                delta={kpis.items_sold.delta}
                                format={formatNumber}
                                icon={Package}
                            />
                            <CompactKpiCard
                                label={t('Avg Basket')}
                                value={kpis.avg_basket.value}
                                delta={kpis.avg_basket.delta}
                                format={formatMoney}
                                icon={ShoppingBag}
                            />

                            {can.view_financials && (
                                <Card className="h-full border-emerald-500/30 bg-emerald-500/5 shadow-none">
                                    <CardContent className="flex h-full flex-col justify-between gap-2 p-3">
                                        <div className="flex items-center justify-between text-emerald-700 dark:text-emerald-400">
                                            <span className="text-xs font-medium">
                                                {t('Gross Profit')}
                                            </span>
                                            <TrendingUp className="size-3.5 shrink-0" />
                                        </div>
                                        <div className="flex items-baseline justify-between gap-1">
                                            <span className="text-lg font-semibold tracking-tight text-emerald-700 tabular-nums dark:text-emerald-400">
                                                {formatMoney(
                                                    gross_profit.amount,
                                                )}
                                            </span>
                                            <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                {gross_profit.margin}%
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        {/* Branch order pipeline + stock value (management visibility) */}
                        {(can.orders || (can.stock && stock_value)) && (
                            <div className="grid gap-3 lg:grid-cols-3">
                                {can.orders && (
                                    <Card className="border-border/60 shadow-none lg:col-span-2">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-sm font-semibold">
                                                {t('Order Pipeline')}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                                {[
                                                    [
                                                        'pending_approval',
                                                        'Pending Approval',
                                                    ],
                                                    ['picking', 'Picking'],
                                                    [
                                                        'ready_for_dispatch',
                                                        'Ready for Dispatch',
                                                    ],
                                                    [
                                                        'dispatched',
                                                        'In Transit',
                                                    ],
                                                    [
                                                        'discrepancy_review',
                                                        'Discrepancy Review',
                                                    ],
                                                ].map(([stage, label]) => {
                                                    const count =
                                                        order_pipeline[stage] ??
                                                        0;
                                                    return (
                                                        <Link
                                                            key={stage}
                                                            href={
                                                                orders.index()
                                                                    .url
                                                            }
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                router.get(
                                                                    orders.index()
                                                                        .url,
                                                                    {
                                                                        status: stage,
                                                                    },
                                                                    {
                                                                        preserveState: true,
                                                                    },
                                                                );
                                                            }}
                                                            className="hover:bg-muted/50 flex flex-col rounded-lg border p-2 transition-colors"
                                                        >
                                                            <span className="text-xl font-semibold tabular-nums">
                                                                {count}
                                                            </span>
                                                            <span className="text-muted-foreground text-[11px] leading-tight">
                                                                {label}
                                                            </span>
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                                {can.stock && stock_value && (
                                    <Card className="border-border/60 shadow-none">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-sm font-semibold">
                                                {t('Stock Value')}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-2xl font-bold tracking-tight tabular-nums">
                                                {formatMoney(stock_value.total)}
                                            </p>
                                            <ul className="text-muted-foreground mt-2 space-y-1 text-xs">
                                                {stock_value.by_location
                                                    .slice(0, 4)
                                                    .map((loc) => (
                                                        <li
                                                            key={loc.id}
                                                            className="flex items-center justify-between gap-2"
                                                        >
                                                            <span>
                                                                {loc.name}
                                                            </span>
                                                            <span className="tabular-nums">
                                                                {formatMoney(
                                                                    loc.value,
                                                                )}
                                                            </span>
                                                        </li>
                                                    ))}
                                                {stock_value.by_location
                                                    .length === 0 && (
                                                    <li>
                                                        {t('No stock found.')}
                                                    </li>
                                                )}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        )}

                        {/* Consolidated financial summary */}
                        {can.view_financials && (
                            <Card className="border-border/60 shadow-none">
                                <CardContent className="p-0">
                                    <div className="grid grid-cols-2 sm:grid-cols-4">
                                        <FinanceStat
                                            icon={RefreshCw}
                                            label={t('Refunds')}
                                            value={formatMoney(refunds.amount)}
                                            sub={t('{{count}} Receipts', {
                                                count: refunds.count,
                                            })}
                                            tone="rose"
                                        />
                                        <FinanceStat
                                            icon={TicketPercent}
                                            label={t('Discounts & Coupons')}
                                            value={formatMoney(
                                                promotions.discount,
                                            )}
                                            sub={t('{{count}} Coupons', {
                                                count: promotions.coupons_used,
                                            })}
                                            tone="amber"
                                        />
                                        {customers && (
                                            <FinanceStat
                                                icon={Wallet}
                                                label={t('Customer Debts')}
                                                value={formatMoney(
                                                    customers.credit_outstanding,
                                                )}
                                                tone="rose"
                                            />
                                        )}
                                        {customers && (
                                            <FinanceStat
                                                icon={Building2}
                                                label={t('Layaway Deposits')}
                                                value={formatMoney(
                                                    customers.layaway_balance,
                                                )}
                                                sub={t('{{count}} Active', {
                                                    count: customers.active_layaways,
                                                })}
                                                tone="blue"
                                            />
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Trend + payment split */}
                        {can.view_financials && (
                            <div className="grid gap-3 lg:grid-cols-3">
                                <Card className="shadow-none lg:col-span-2">
                                    <CardHeader className="p-3 pb-0">
                                        <CardTitle className="text-xs font-semibold">
                                            {t('Sales Trend')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-3">
                                        <div className="h-[200px] w-full">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <ComposedChart
                                                    data={trend}
                                                    margin={{
                                                        top: 5,
                                                        right: 5,
                                                        left: -25,
                                                        bottom: 0,
                                                    }}
                                                >
                                                    <CartesianGrid
                                                        strokeDasharray="3 3"
                                                        vertical={false}
                                                        stroke="var(--border)"
                                                    />
                                                    <XAxis
                                                        dataKey="label"
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tick={{ fontSize: 9 }}
                                                    />
                                                    <YAxis
                                                        yAxisId="rev"
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tickFormatter={(v) =>
                                                            compact(v)
                                                        }
                                                        tick={{ fontSize: 9 }}
                                                    />
                                                    <YAxis
                                                        yAxisId="ord"
                                                        orientation="right"
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tick={{ fontSize: 9 }}
                                                    />
                                                    <Tooltip
                                                        content={
                                                            <CustomTooltip
                                                                formatter={(
                                                                    v: any,
                                                                ) =>
                                                                    formatMoney(
                                                                        Number(
                                                                            v,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        }
                                                    />
                                                    <Area
                                                        yAxisId="rev"
                                                        type="monotone"
                                                        dataKey="revenue"
                                                        stroke={CHART.revenue}
                                                        fill={CHART.revenue}
                                                        fillOpacity={0.1}
                                                        strokeWidth={1.5}
                                                    />
                                                    <Line
                                                        yAxisId="ord"
                                                        type="monotone"
                                                        dataKey="transactions"
                                                        stroke={
                                                            CHART.transactions
                                                        }
                                                        strokeWidth={1.5}
                                                        dot={false}
                                                    />
                                                </ComposedChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="shadow-none">
                                    <CardHeader className="p-3 pb-0">
                                        <CardTitle className="text-xs font-semibold">
                                            {t('Payment Methods')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-3">
                                        <div className="relative h-[130px] w-full">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <PieChart>
                                                    <Pie
                                                        data={payment_split}
                                                        dataKey="amount"
                                                        nameKey="label"
                                                        innerRadius={35}
                                                        outerRadius={55}
                                                        strokeWidth={0}
                                                    >
                                                        {payment_split.map(
                                                            (entry) => (
                                                                <Cell
                                                                    key={
                                                                        entry.method
                                                                    }
                                                                    fill={
                                                                        entry.color
                                                                    }
                                                                />
                                                            ),
                                                        )}
                                                    </Pie>
                                                    <Tooltip
                                                        content={
                                                            <CustomTooltip
                                                                formatter={(
                                                                    v: any,
                                                                ) =>
                                                                    formatMoney(
                                                                        Number(
                                                                            v,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        }
                                                    />
                                                </PieChart>
                                            </ResponsiveContainer>
                                            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                                <span className="text-sm font-semibold tabular-nums">
                                                    {compact(totalPayments)}
                                                </span>
                                                <span className="text-muted-foreground text-[10px]">
                                                    {t('total')}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="mt-2 grid grid-cols-2 gap-1.5 border-t pt-2 text-[11px]">
                                            {payment_split.map((entry) => (
                                                <div
                                                    key={entry.method}
                                                    className="flex items-center justify-between"
                                                >
                                                    <span className="text-muted-foreground flex items-center gap-1">
                                                        <span
                                                            className="size-1.5 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    entry.color,
                                                            }}
                                                        />
                                                        {entry.label}
                                                    </span>
                                                    <span className="font-medium tabular-nums">
                                                        {entry.percentage}%
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* Categories / hourly / weekday breakdown */}
                        {can.view_financials && (
                            <div className="grid gap-3 lg:grid-cols-3">
                                <Card className="shadow-none">
                                    <CardHeader className="p-3 pb-0">
                                        <CardTitle className="text-xs font-semibold">
                                            {t('Sales by Category')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-3">
                                        <div className="h-[150px] w-full">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <BarChart
                                                    data={categories.slice(
                                                        0,
                                                        5,
                                                    )}
                                                    layout="vertical"
                                                    margin={{
                                                        top: 0,
                                                        right: 5,
                                                        left: 10,
                                                        bottom: 0,
                                                    }}
                                                >
                                                    <XAxis type="number" hide />
                                                    <YAxis
                                                        dataKey="category"
                                                        type="category"
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tick={{ fontSize: 9 }}
                                                        width={70}
                                                    />
                                                    <Tooltip
                                                        content={
                                                            <CustomTooltip
                                                                formatter={(
                                                                    v: any,
                                                                ) =>
                                                                    formatMoney(
                                                                        Number(
                                                                            v,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        }
                                                    />
                                                    <Bar
                                                        dataKey="revenue"
                                                        fill={CHART.categories}
                                                        radius={[0, 4, 4, 0]}
                                                    />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="shadow-none">
                                    <CardHeader className="p-3 pb-0">
                                        <CardTitle className="text-xs font-semibold">
                                            {t('Working Hours')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-3">
                                        <div className="h-[150px] w-full">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <BarChart
                                                    data={hourly}
                                                    margin={{
                                                        top: 0,
                                                        right: 0,
                                                        left: -25,
                                                        bottom: 0,
                                                    }}
                                                >
                                                    <CartesianGrid
                                                        strokeDasharray="3 3"
                                                        vertical={false}
                                                    />
                                                    <XAxis
                                                        dataKey="label"
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tick={{ fontSize: 8 }}
                                                    />
                                                    <YAxis
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tickFormatter={(v) =>
                                                            compact(v)
                                                        }
                                                        tick={{ fontSize: 8 }}
                                                    />
                                                    <Tooltip
                                                        content={
                                                            <CustomTooltip
                                                                formatter={(
                                                                    v: any,
                                                                ) =>
                                                                    formatMoney(
                                                                        Number(
                                                                            v,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        }
                                                    />
                                                    <Bar
                                                        dataKey="revenue"
                                                        radius={[2, 2, 0, 0]}
                                                    >
                                                        {hourly.map((h) => (
                                                            <Cell
                                                                key={h.hour}
                                                                fill={
                                                                    h.hour ===
                                                                    currentHour
                                                                        ? CHART.hourly
                                                                        : CHART.hourlyMuted
                                                                }
                                                            />
                                                        ))}
                                                    </Bar>
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="shadow-none">
                                    <CardHeader className="p-3 pb-0">
                                        <CardTitle className="text-xs font-semibold">
                                            {t('Days of Week')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-3">
                                        <div className="h-[150px] w-full">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <BarChart
                                                    data={weekdays}
                                                    margin={{
                                                        top: 0,
                                                        right: 0,
                                                        left: -25,
                                                        bottom: 0,
                                                    }}
                                                >
                                                    <CartesianGrid
                                                        strokeDasharray="3 3"
                                                        vertical={false}
                                                    />
                                                    <XAxis
                                                        dataKey="label"
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tick={{ fontSize: 8 }}
                                                    />
                                                    <YAxis
                                                        tickLine={false}
                                                        axisLine={false}
                                                        tickFormatter={(v) =>
                                                            compact(v)
                                                        }
                                                        tick={{ fontSize: 8 }}
                                                    />
                                                    <Tooltip
                                                        content={
                                                            <CustomTooltip
                                                                formatter={(
                                                                    v: any,
                                                                ) =>
                                                                    formatMoney(
                                                                        Number(
                                                                            v,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        }
                                                    />
                                                    <Bar
                                                        dataKey="revenue"
                                                        fill={CHART.weekday}
                                                        radius={[2, 2, 0, 0]}
                                                    />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* Branch performance */}
                        {can.view_financials && branches.length > 0 && (
                            <Card className="shadow-none">
                                <CardHeader className="p-3 pb-1">
                                    <CardTitle className="text-xs font-semibold">
                                        {t('Branch Performance')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="border-b hover:bg-transparent">
                                                <TableHead className="h-7 text-[11px]">
                                                    {t('Branch')}
                                                </TableHead>
                                                <TableHead className="h-7 text-right text-[11px]">
                                                    {t('Transactions')}
                                                </TableHead>
                                                <TableHead className="h-7 text-right text-[11px]">
                                                    {t('Sales')}
                                                </TableHead>
                                                <TableHead className="h-7 text-right text-[11px]">
                                                    {t('Share')}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {branches.map((b, i) => (
                                                <TableRow
                                                    key={b.id}
                                                    className="h-8"
                                                >
                                                    <TableCell className="py-1 text-xs font-medium">
                                                        <span className="text-muted-foreground mr-1.5 text-[10px] tabular-nums">
                                                            {i + 1}
                                                        </span>
                                                        {b.name}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground py-1 text-right text-xs tabular-nums">
                                                        {formatNumber(
                                                            b.transactions,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="py-1 text-right text-xs font-semibold tabular-nums">
                                                        {formatMoney(b.revenue)}
                                                    </TableCell>
                                                    <TableCell className="py-1 text-right text-xs">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Progress
                                                                value={b.share}
                                                                className="h-1.5 w-12"
                                                            />
                                                            <span className="text-muted-foreground w-8 text-[10px] tabular-nums">
                                                                {b.share}%
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}

                {/* Operations & stock alerts */}
                {can.view_stock_alerts && hasAlerts && alerts && (
                    <Card className="border-border/60 shadow-none">
                        <CardContent className="flex flex-col gap-3 p-3">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div className="text-foreground flex items-start gap-2 text-xs font-medium">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
                                    <span>{t('Stock health')}</span>
                                </div>
                                <div className="text-muted-foreground flex flex-wrap items-center gap-3 text-[11px]">
                                    {alerts.out_of_stock > 0 && (
                                        <span className="flex items-center gap-1">
                                            <Package className="size-3" />{' '}
                                            {t('{{count}} Out of stock', {
                                                count: alerts.out_of_stock,
                                            })}
                                        </span>
                                    )}
                                    {alerts.parked > 0 && (
                                        <span className="flex items-center gap-1">
                                            <Package className="size-3" />{' '}
                                            {t('{{count}} Parked', {
                                                count: alerts.parked,
                                            })}
                                        </span>
                                    )}
                                    {alerts.open_shifts > 0 && (
                                        <span className="flex items-center gap-1">
                                            <Clock className="size-3" />{' '}
                                            {t('{{count}} Open shifts', {
                                                count: alerts.open_shifts,
                                            })}
                                        </span>
                                    )}
                                    {alerts.active_layaways > 0 && (
                                        <span className="flex items-center gap-1">
                                            <Building2 className="size-3" />{' '}
                                            {t('{{count}} Active layaways', {
                                                count: alerts.active_layaways,
                                            })}
                                        </span>
                                    )}
                                    {can.restock &&
                                        alerts.pending_stock_requests > 0 && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-6 border-amber-500/40 px-2 text-[11px]"
                                                asChild
                                            >
                                                <Link
                                                    href={stock.requests().url}
                                                >
                                                    {t(
                                                        '{{count}} Pending stock requests',
                                                        {
                                                            count: alerts.pending_stock_requests,
                                                        },
                                                    )}
                                                    <ChevronRight className="ml-1 size-3" />
                                                </Link>
                                            </Button>
                                        )}
                                </div>
                            </div>

                            {/* Tier 3 — godown (supply level) alerts, most urgent */}
                            {alerts.godown_alerts.length > 0 && (
                                <div className="flex flex-col gap-1.5 rounded-md border border-rose-500/30 bg-rose-500/10 p-2">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="flex items-center gap-1.5 text-[11px] font-semibold text-rose-700 dark:text-rose-400">
                                            <AlertTriangle className="size-3.5" />
                                            {t('Godown alerts')}
                                        </span>
                                        <Button
                                            size="sm"
                                            className="h-6 px-2 text-[11px]"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    purchaseOrders.index().url
                                                }
                                            >
                                                {t('Order stock')}{' '}
                                                <ChevronRight className="ml-1 size-3" />
                                            </Link>
                                        </Button>
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        {alerts.godown_alerts
                                            .slice(0, 3)
                                            .map((g) => (
                                                <p
                                                    key={g.sku}
                                                    className="text-muted-foreground text-[11px]"
                                                >
                                                    <span className="font-medium text-rose-700 dark:text-rose-400">
                                                        {g.godown_name}
                                                    </span>
                                                    {t(' is low on ')}
                                                    <span className="text-foreground font-medium">
                                                        {g.product_name}
                                                    </span>
                                                    {t(
                                                        ' ({{qty}} left, reorder at {{level}}).',
                                                        {
                                                            qty: formatNumber(
                                                                g.quantity,
                                                            ),
                                                            level: g.reorder_level,
                                                        },
                                                    )}
                                                </p>
                                            ))}
                                        {alerts.godown_alerts.length > 3 && (
                                            <p className="text-muted-foreground text-[11px]">
                                                {t('+{{count}} more', {
                                                    count:
                                                        alerts.godown_alerts
                                                            .length - 3,
                                                })}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Tier 2 — category roll-up alerts per location */}
                            {alerts.category_alerts.length > 0 && (
                                <div className="flex flex-col gap-1.5">
                                    <span className="text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                                        {t('Category alerts')}
                                    </span>
                                    <div className="flex flex-wrap gap-1.5">
                                        {alerts.category_alerts
                                            .slice(0, 4)
                                            .map((c, index) => (
                                                <span
                                                    key={`${c.category}-${c.location_id}-${index}`}
                                                    className="bg-background inline-flex items-center gap-1 rounded-md border border-amber-500/30 px-2 py-1 text-[11px]"
                                                >
                                                    <span className="font-medium">
                                                        {c.category}
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {t(
                                                            '{{location}}: {{percent}}% of normal stock',
                                                            {
                                                                location:
                                                                    locationLabel(
                                                                        {
                                                                            name: c.location_name,
                                                                            type: c.location_type,
                                                                        },
                                                                    ),
                                                                percent:
                                                                    c.percent_of_normal,
                                                            },
                                                        )}
                                                    </span>
                                                </span>
                                            ))}
                                        {alerts.category_alerts.length > 4 && (
                                            <span className="text-muted-foreground self-center text-[11px]">
                                                {t('+{{count}} more', {
                                                    count:
                                                        alerts.category_alerts
                                                            .length - 4,
                                                })}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Tier 1 — one product, one location */}
                            {alerts.product_alerts.length > 0 && (
                                <div className="flex flex-col gap-1.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                                            {t('Low stock')}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="h-6 px-2 text-[11px]"
                                            asChild
                                        >
                                            <Link href={stock.index().url}>
                                                {t('View Stock')}{' '}
                                                <ChevronRight className="ml-1 size-3" />
                                            </Link>
                                        </Button>
                                    </div>
                                    <div className="flex flex-wrap gap-1.5">
                                        {alerts.product_alerts
                                            .slice(0, 4)
                                            .map((item) => (
                                                <span
                                                    key={`${item.id}-${item.location_id}`}
                                                    className="bg-background inline-flex items-center gap-1 rounded-md border border-amber-500/30 px-2 py-1 text-[11px]"
                                                >
                                                    <span className="font-medium">
                                                        {item.name}
                                                    </span>
                                                    <span className="text-muted-foreground tabular-nums">
                                                        {t(
                                                            '{{qty}} of {{level}}',
                                                            {
                                                                qty: formatNumber(
                                                                    item.quantity,
                                                                ),
                                                                level: item.reorder_level,
                                                            },
                                                        )}
                                                    </span>
                                                    <span
                                                        className={`rounded px-1 text-[9px] font-medium uppercase ${
                                                            item.location_type ===
                                                            'godown'
                                                                ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                                                : 'bg-muted text-muted-foreground'
                                                        }`}
                                                    >
                                                        {item.location_type ===
                                                        'godown'
                                                            ? 'Godown'
                                                            : 'Store'}{' '}
                                                        · {item.location_name}
                                                    </span>
                                                </span>
                                            ))}
                                        {alerts.product_alerts.length > 4 && (
                                            <span className="text-muted-foreground self-center text-[11px]">
                                                {t('+{{count}} more', {
                                                    count:
                                                        alerts.product_alerts
                                                            .length - 4,
                                                })}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Recent sales & top products */}
                {can.sales && (
                    <div className="grid gap-3 lg:grid-cols-2">
                        <Card className="shadow-none">
                            <CardHeader className="p-3 pb-1">
                                <CardTitle className="text-xs font-semibold">
                                    {t('Recent Sales')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="h-7 text-[11px]">
                                                {t('Receipt')}
                                            </TableHead>
                                            <TableHead className="h-7 text-[11px]">
                                                {t('Customer')}
                                            </TableHead>
                                            <TableHead className="h-7 text-[11px]">
                                                {t('Status')}
                                            </TableHead>
                                            <TableHead className="h-7 text-right text-[11px]">
                                                {t('Amount')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recent_sales
                                            .slice(0, 5)
                                            .map((sale) => (
                                                <TableRow
                                                    key={sale.id}
                                                    className="h-8"
                                                >
                                                    <TableCell className="py-1 text-xs">
                                                        <Link
                                                            href={
                                                                sales.show({
                                                                    sale: sale.id,
                                                                }).url
                                                            }
                                                            className="font-medium hover:underline"
                                                        >
                                                            {sale.sale_no}
                                                        </Link>
                                                        <span className="text-muted-foreground ml-1.5 text-[9px]">
                                                            {timeOf(
                                                                sale.created_at,
                                                            )}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground py-1 text-xs">
                                                        {sale.customer ??
                                                            t('Walk-in')}
                                                    </TableCell>
                                                    <TableCell className="py-1 text-xs">
                                                        <Badge
                                                            variant="outline"
                                                            className={`h-4 px-1 text-[9px] font-normal ${paymentStatusTone(sale.payment_status)}`}
                                                        >
                                                            {
                                                                sale.payment_status
                                                            }
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="py-1 text-right text-xs font-bold tabular-nums">
                                                        {formatMoney(
                                                            sale.total,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {can.view_financials && (
                            <Card className="shadow-none">
                                <CardHeader className="p-3 pb-1">
                                    <CardTitle className="text-xs font-semibold">
                                        {t('Top Products')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead className="h-7 text-[11px]">
                                                    {t('Product')}
                                                </TableHead>
                                                <TableHead className="h-7 text-right text-[11px]">
                                                    {t('Qty')}
                                                </TableHead>
                                                <TableHead className="h-7 text-right text-[11px]">
                                                    {t('Total')}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {top_products
                                                .slice(0, 5)
                                                .map((product) => (
                                                    <TableRow
                                                        key={product.sku}
                                                        className="h-8"
                                                    >
                                                        <TableCell className="py-1 text-xs">
                                                            <span className="font-medium">
                                                                {product.name}
                                                            </span>
                                                            <span className="text-muted-foreground ml-1 text-[9px]">
                                                                ({product.sku})
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="py-1 text-right text-xs font-medium tabular-nums">
                                                            {formatNumber(
                                                                product.qty,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="py-1 text-right text-xs font-bold text-emerald-600 tabular-nums dark:text-emerald-400">
                                                            {formatMoney(
                                                                product.revenue,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Top customers & system activity */}
                <div className="grid gap-3 lg:grid-cols-2">
                    {can.view_customers &&
                        customers &&
                        customers.top_customers.length > 0 && (
                            <Card className="shadow-none">
                                <CardHeader className="p-3 pb-1">
                                    <CardTitle className="text-xs font-semibold">
                                        {t('Top Customers')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead className="h-7 text-[11px]">
                                                    {t('Customer')}
                                                </TableHead>
                                                <TableHead className="h-7 text-[11px]">
                                                    {t('Type')}
                                                </TableHead>
                                                <TableHead className="h-7 text-right text-[11px]">
                                                    {t('Points')}
                                                </TableHead>
                                                <TableHead className="h-7 text-right text-[11px]">
                                                    {t('Lifetime Spend')}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {customers.top_customers
                                                .slice(0, 5)
                                                .map((c) => (
                                                    <TableRow
                                                        key={c.id}
                                                        className="h-8"
                                                    >
                                                        <TableCell className="py-1 text-xs font-medium">
                                                            {c.name}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground py-1 text-xs">
                                                            {c.customer_type}
                                                        </TableCell>
                                                        <TableCell className="py-1 text-right text-xs tabular-nums">
                                                            {formatNumber(
                                                                c.loyalty_points,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="py-1 text-right text-xs font-bold tabular-nums">
                                                            {formatMoney(
                                                                c.lifetime_spend,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        )}

                    {can.activity && activity && activity.length > 0 && (
                        <Card className="shadow-none">
                            <CardHeader className="p-3 pb-1">
                                <CardTitle className="text-xs font-semibold">
                                    {t('System Activity')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-3 pt-1">
                                <div className="flex flex-col">
                                    {activity.slice(0, 5).map((act, i) => (
                                        <div
                                            key={act.id}
                                            className="relative flex items-start gap-2.5 pb-3 pl-1 text-xs last:pb-0"
                                        >
                                            {i !==
                                                activity.slice(0, 5).length -
                                                    1 && (
                                                <span className="bg-border absolute top-3 left-[7px] h-full w-px" />
                                            )}
                                            <Activity className="text-muted-foreground mt-0.5 size-3 shrink-0" />
                                            <div className="flex min-w-0 flex-1 items-start justify-between gap-2">
                                                <p className="min-w-0">
                                                    <span className="text-foreground font-medium">
                                                        {act.user}
                                                    </span>{' '}
                                                    <span className="text-muted-foreground">
                                                        {act.description}
                                                    </span>
                                                </p>
                                                <span className="text-muted-foreground shrink-0 text-[10px] whitespace-nowrap">
                                                    {timeOf(act.created_at)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Finance widget row — only rendered for the Finance role and up */}
                {finance && (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <FinanceCard
                            label="Pending refunds"
                            value={String(finance.pending_refunds)}
                            to="/finance/refunds"
                        />
                        <FinanceCard
                            label="Voids (24h)"
                            value={String(finance.voids_24h)}
                            detail={`${finance.flagged_voids_24h} flagged`}
                            to="/finance/voids"
                        />
                        <FinanceCard
                            label="Cash variances today"
                            value={String(finance.cash_variances_today)}
                            detail={`${finance.pending_cash_outs} cash-outs pending approval`}
                            to="/finance/drawer"
                        />
                        <FinanceCard
                            label="Receivables / Payables"
                            value={formatMoney(finance.receivables)}
                            detail={`payables ${formatMoney(finance.payables)}`}
                            to="/finance/accounting"
                        />
                    </div>
                )}
            </div>
        </>
    );
}

function FinanceCard({
    label,
    value,
    detail,
    to,
}: {
    label: string;
    value: string;
    detail?: string;
    to: string;
}) {
    return (
        <Link
            href={to}
            className="hover:bg-accent/40 rounded-xl border p-4 transition-colors"
        >
            <div className="text-muted-foreground text-xs font-medium">
                {label}
            </div>
            <div className="text-2xl font-semibold tabular-nums">{value}</div>
            {detail && (
                <div className="text-muted-foreground mt-0.5 text-xs">
                    {detail}
                </div>
            )}
        </Link>
    );
}

AdvancedDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
