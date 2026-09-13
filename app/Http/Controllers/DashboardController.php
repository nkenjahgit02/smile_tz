<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchOrder;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\Layaway;
use App\Models\ParkedSale;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\RestockRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\SupplierInvoice;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    private const PAYMENT_LABELS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'mobile' => 'Mobile',
        'bank_transfer' => 'Bank Transfer',
        'loyalty' => 'Loyalty',
        'credit' => 'Credit',
        'deposit' => 'Deposit',
    ];

    private const PAYMENT_COLORS = [
        'cash' => '#16a34a',
        'card' => '#2563eb',
        'mobile' => '#7c3aed',
        'bank_transfer' => '#0d9488',
        'loyalty' => '#d97706',
        'credit' => '#dc2626',
        'deposit' => '#64748b',
    ];

    private const WEEKDAY_LABELS = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ];

    private function drive(): string
    {
        return DB::connection()->getDriverName();
    }

    private function expr(string $sqlite, string $mysql): string
    {
        return $this->drive() === 'sqlite' ? $sqlite : $mysql;
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $locationIds = $user->scopedLocationIds();
        $branchIds = $this->selectedBranchIds($request, collect($locationIds));

        [$start, $end] = $this->parseDateRange($request);
        $length = (int) $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $start->copy()->subDays($length);
        $globalFilters = $request->only(['product', 'status', 'branch']);
        $filters = [
            'product' => $request->filled('product') ? (int) $request->query('product') : null,
            'status' => $request->filled('status') && in_array($request->query('status'), ['active', 'returned', 'voided'], true) ? $request->query('status') : null,
        ];

        // Sellers only ever see their own sales, never the consolidated picture.
        $scopeUser = $user->hasRole('Seller') ? $user->id : null;

        $can = [
            'view_financials' => $user->can('view-finance-reports'),
            'view_stock_alerts' => $user->can('stock.view'),
            'view_stock_tier2_tier3' => $user->can('stock.adjust'),
            'view_cross_location' => $user->seesAllLocations(),
            'view_customers' => $user->can('view-finance-reports') || $user->can('stock.adjust'),
            'view_activity' => $user->hasAnyRole(['Owner', 'Admin']) || $user->hasRole('Finance'),
            'own_sales_only' => $user->hasRole('Seller'),
            'sales' => $user->can('sales.view'),
            'stock' => $user->can('stock.view'),
            'customers' => $user->can('view-finance-reports') || $user->can('stock.adjust'),
            'activity' => $user->hasAnyRole(['Owner', 'Admin']) || $user->hasRole('Finance'),
            'restock' => $user->can('stock.restock.approve'),
            'orders' => $user->can('orders.view'),
        ];

        $stats = $this->rangeStats($branchIds, $start, $end, $scopeUser, $filters);
        $prevStats = $this->rangeStats($branchIds, $prevStart, $prevEnd, $scopeUser, $filters);

        return Inertia::render('dashboard', [
            'selected_branch_id' => count($branchIds) === 1 && count($locationIds) > 1 ? $branchIds[0] : null,
            'filter' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
                'period_key' => $this->periodKey($start, $end),
                'available_years' => $this->availableYears($branchIds),
            ],
            'kpis' => [
                'revenue' => ['value' => round($stats['revenue'], 2), 'delta' => $this->delta($stats['revenue'], $prevStats['revenue'])],
                'transactions' => ['value' => $stats['transactions'], 'delta' => $this->delta($stats['transactions'], $prevStats['transactions'])],
                'items_sold' => ['value' => round($stats['items'], 2), 'delta' => $this->delta($stats['items'], $prevStats['items'])],
                'avg_basket' => ['value' => round($this->avg($stats), 2), 'delta' => $this->delta($this->avg($stats), $this->avg($prevStats))],
            ],
            // The golden rule: a role that may not see a piece of data never
            // receives it in the Inertia payload at all (null / empty arrays),
            // not merely a "hide me" flag for the frontend.
            'gross_profit' => $can['view_financials'] ? $this->grossProfit($branchIds, $start, $end, $filters) : null,
            'refunds' => $can['view_financials']
                ? ['count' => (int) $this->rangeRefunds($branchIds, $start, $end, $filters)->count, 'amount' => round((float) $this->rangeRefunds($branchIds, $start, $end, $filters)->amount, 2)]
                : null,
            'promotions' => $can['view_financials']
                ? ['coupons_used' => (int) $this->rangePromotions($branchIds, $start, $end, $filters)->used, 'discount' => round((float) $this->rangePromotions($branchIds, $start, $end, $filters)->discount, 2)]
                : null,
            'payment_split' => $can['view_financials'] ? $this->paymentSplit($branchIds, $start, $end, $filters) : [],
            'trend' => $can['view_financials'] ? $this->trend($branchIds, $start, $end, $filters) : [],
            'categories' => $can['view_financials'] ? $this->categoryBreakdown($branchIds, $start, $end, $filters) : [],
            'hourly' => $can['view_financials'] ? $this->hourlyDistribution($branchIds, $start, $end, $filters) : [],
            'weekdays' => $can['view_financials'] ? $this->weekdayDistribution($branchIds, $start, $end, $filters) : [],
            'branches' => $can['view_financials'] ? $this->branchSplit($branchIds, $start, $end, $filters) : [],
            'recent_sales' => $can['sales'] ? $this->recentSales($branchIds, $start, $end, 8, $scopeUser, $filters) : [],
            'top_products' => $can['view_financials'] ? $this->topProducts($branchIds, $start, $end, 8, $filters) : [],
            'alerts' => $can['view_stock_alerts'] ? $this->alerts($branchIds, $can['view_stock_tier2_tier3']) : null,
            'locations' => $user->seesAllLocations() ? $this->locations($user) : [],
            'customers' => $can['view_customers'] ? $this->customerHighlights($branchIds, $start, $end) : null,
            'activity' => $can['view_activity']
                ? ($user->hasAnyRole(['Owner', 'Admin'])
                    ? $this->recentActivity($branchIds, 6)
                    : $this->recentFinancialActivity($branchIds, 6))
                : [],
            'finance' => $can['view_financials'] ? $this->financePositions($branchIds) : null,
            'order_pipeline' => $can['orders'] ? $this->orderPipeline($locationIds) : [],
            'stock_value' => $can['stock'] ? $this->stockValue($locationIds) : null,
            'global_filters' => $globalFilters,
            'products' => $this->filterProducts($locationIds),
            'can' => $can,
        ]);
    }

    private function selectedBranchIds(Request $request, $accessible): array
    {
        $ids = $accessible->map(fn ($v) => (int) $v)->values();

        if ($request->filled('branch') && $ids->contains((int) $request->query('branch'))) {
            return [(int) $request->query('branch')];
        }

        return $ids->all();
    }

    private function parseDateRange(Request $request): array
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        if ($from === null && $to === null) {
            $start = now()->startOfDay();
            $end = now()->endOfDay();

            return [$start, $end];
        }

        $start = $from ?? $to->copy()->startOfDay();
        $end = $to ?? $from->copy()->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
    }

    private function parseDate($value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return null;
        }

        [$y, $m, $d] = array_map('intval', explode('-', trim($value)));

        if (! checkdate($m, $d, $y)) {
            return null;
        }

        return Carbon::create($y, $m, $d)->startOfDay();
    }

    private function periodKey(CarbonInterface $start, CarbonInterface $end): string
    {
        $length = (int) $start->diffInDays($end) + 1;
        $today = now();

        if ($length === 1 && $start->isSameDay($today)) {
            return 'today';
        }
        if ($length === 7 && $end->isSameDay($today) && $start->isSameDay($today->copy()->subDays(6))) {
            return '7d';
        }
        if ($length === 30 && $end->isSameDay($today) && $start->isSameDay($today->copy()->subDays(29))) {
            return '30d';
        }
        if ($length === 90 && $end->isSameDay($today) && $start->isSameDay($today->copy()->subDays(89))) {
            return '90d';
        }
        if ($start->isSameDay($today->copy()->startOfYear()) && $end->isSameDay($today)) {
            return 'ytd';
        }
        if ($start->format('m-d') === '01-01' && $end->format('m-d') === '12-31' && $start->year === $end->year) {
            return 'year';
        }

        return 'custom';
    }

    private function availableYears(array $branchIds): array
    {
        $years = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->selectRaw($this->expr("distinct strftime('%Y', created_at) as y", 'distinct year(created_at) as y'))
            ->orderBy('y')
            ->pluck('y')
            ->map(fn ($v) => (int) $v)
            ->all();

        return $years ?: [now()->year];
    }

    private function rangeStats(array $branchIds, CarbonInterface $start, CarbonInterface $end, ?int $scopeUserId = null, array $filters = []): array
    {
        $status = $filters['status'] ?? 'active';

        $base = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', $status)
            ->whereBetween('created_at', [$start, $end])
            ->when($scopeUserId, fn ($q) => $q->where('user_id', $scopeUserId))
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId));

        $revenue = (float) (clone $base)->sum('total');
        $transactions = (clone $base)->count();
        $items = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', $status)
            ->whereBetween('sales.created_at', [$start, $end])
            ->when($scopeUserId, fn ($q) => $q->where('sales.user_id', $scopeUserId))
            ->when($filters['product'] ?? null, fn ($q, $productId) => $q->where('sale_items.product_id', (int) $productId))
            ->sum('sale_items.quantity');

        return [
            'revenue' => $revenue,
            'transactions' => $transactions,
            'items' => $items,
        ];
    }

    private function avg(array $stats): float
    {
        return $stats['transactions'] > 0 ? $stats['revenue'] / $stats['transactions'] : 0.0;
    }

    private function delta(float $current, float $previous): ?float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }

        return $current > 0 ? null : 0.0;
    }

    private function grossProfit(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $profit = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', $filters['status'] ?? 'active')
            ->whereBetween('sales.created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $q->where('sale_items.product_id', (int) $productId))
            ->sum('sale_items.profit');

        $revenue = (float) Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', $filters['status'] ?? 'active')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->sum('total');

        return [
            'amount' => round($profit, 2),
            'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0,
        ];
    }

    private function rangeRefunds(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = [])
    {
        return Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', $filters['status'] ?? 'returned')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->selectRaw('count(*) as count, coalesce(sum(total),0) as amount')
            ->first();
    }

    private function rangePromotions(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = [])
    {
        return DB::table('sale_coupons')
            ->join('sales', 'sales.id', '=', 'sale_coupons.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', $filters['status'] ?? 'active')
            ->whereBetween('sales.created_at', [$start, $end])
            ->when($filters['product'] ?? null, function ($q, $productId) {
                $q->whereExists(function ($sub) use ($productId) {
                    $sub->selectRaw('1')
                        ->from('sale_items')
                        ->whereColumn('sale_items.sale_id', 'sales.id')
                        ->where('sale_items.product_id', (int) $productId);
                });
            })
            ->selectRaw('count(*) as used, coalesce(sum(sale_coupons.discount_amount),0) as discount')
            ->first();
    }

    private function paymentSplit(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $totals = Payment::query()
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', $filters['status'] ?? 'active')
            ->whereBetween('payments.created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $q->whereExists(function ($sub) use ($productId) {
                $sub->selectRaw('1')->from('sale_items')
                    ->whereColumn('sale_items.sale_id', 'sales.id')
                    ->where('sale_items.product_id', (int) $productId);
            }))
            ->selectRaw('payments.method as method, coalesce(sum(payments.amount),0) as total')
            ->groupBy('payments.method')
            ->pluck('total', 'method');

        $grandTotal = (float) $totals->sum();

        return collect(self::PAYMENT_LABELS)
            ->map(fn (string $label, string $method) => [
                'method' => $method,
                'label' => $label,
                'color' => self::PAYMENT_COLORS[$method],
                'amount' => round((float) ($totals[$method] ?? 0), 2),
                'percentage' => $grandTotal > 0 ? round(((float) ($totals[$method] ?? 0) / $grandTotal) * 100, 1) : 0,
            ])
            ->reject(fn (array $row) => $row['amount'] <= 0)
            ->values()
            ->all();
    }

    private function trend(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $days = (int) $start->diffInDays($end) + 1;

        if ($days <= 60) {
            return $this->trendByDay($branchIds, $start, $end, $filters);
        }
        if ($days <= 370) {
            return $this->trendByWeek($branchIds, $start, $end, $filters);
        }

        return $this->trendByMonth($branchIds, $start, $end, $filters);
    }

    private function trendByDay(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $rows = Sale::query()
            ->selectRaw('date(created_at) as day, coalesce(sum(total),0) as revenue, count(*) as transactions')
            ->whereIn('branch_id', $branchIds)
            ->where('status', $filters['status'] ?? 'active')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $out = [];

        for ($date = $start->copy(); $date->lte($end); $date = $date->copy()->addDay()) {
            $row = $rows->get($date->format('Y-m-d'));
            $out[] = [
                'label' => $date->format('j M'),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'transactions' => (int) ($row->transactions ?? 0),
            ];
        }

        return $out;
    }

    private function trendByWeek(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $weekExpr = $this->expr(
            "date(created_at, '-' || ((strftime('%w', created_at) + 6) % 7) || ' days')",
            'date_sub(date(created_at), interval weekday(created_at) day)'
        );

        return Sale::query()
            ->selectRaw("{$weekExpr} as week, coalesce(sum(total),0) as revenue, count(*) as transactions")
            ->whereIn('branch_id', $branchIds)
            ->where('status', $filters['status'] ?? 'active')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(fn ($row) => [
                'label' => Carbon::parse($row->week)->format('j M'),
                'revenue' => round((float) $row->revenue, 2),
                'transactions' => (int) $row->transactions,
            ])
            ->all();
    }

    private function trendByMonth(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        return Sale::query()
            ->selectRaw($this->expr("strftime('%Y-%m', created_at)", "date_format(created_at,'%Y-%m')").' as ym, coalesce(sum(total),0) as revenue, count(*) as transactions')
            ->whereIn('branch_id', $branchIds)
            ->where('status', $filters['status'] ?? 'active')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => [
                'label' => Carbon::createFromFormat('Y-m', $row->ym)->format('M y'),
                'revenue' => round((float) $row->revenue, 2),
                'transactions' => (int) $row->transactions,
            ])
            ->all();
    }

    private function categoryBreakdown(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', $filters['status'] ?? 'active')
            ->whereBetween('sales.created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $q->where('sale_items.product_id', (int) $productId))
            ->selectRaw('categories.name as category, coalesce(sum(sale_items.line_total),0) as revenue, coalesce(sum(sale_items.quantity),0) as qty')
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'revenue' => round((float) $row->revenue, 2),
                'qty' => round((float) $row->qty, 1),
            ])
            ->all();
    }

    private function hourlyDistribution(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $rows = Sale::query()
            ->selectRaw($this->expr("strftime('%H', created_at)", 'hour(created_at)').' as h, coalesce(sum(total),0) as revenue')
            ->whereIn('branch_id', $branchIds)
            ->where('status', $filters['status'] ?? 'active')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->groupBy('h')
            ->get()
            ->mapWithKeys(fn ($row) => [str_pad((string) $row->h, 2, '0', STR_PAD_LEFT) => (float) $row->revenue]);

        $out = [];

        for ($h = 0; $h <= 23; $h++) {
            $out[] = [
                'label' => sprintf('%02d:00', $h),
                'hour' => $h,
                'revenue' => round($rows[sprintf('%02d', $h)] ?? 0, 2),
            ];
        }

        return $out;
    }

    private function weekdayDistribution(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $rows = Sale::query()
            ->selectRaw($this->expr("((strftime('%w', created_at) + 6) % 7) + 1", 'dayofweek(created_at)').' as dw, coalesce(sum(total),0) as revenue, count(*) as transactions')
            ->whereIn('branch_id', $branchIds)
            ->where('status', $filters['status'] ?? 'active')
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->groupBy('dw')
            ->get()
            ->keyBy('dw');

        $out = [];

        foreach (self::WEEKDAY_LABELS as $mysqlDay => $label) {
            $out[] = [
                'label' => $label,
                'revenue' => round((float) ($rows[$mysqlDay]->revenue ?? 0), 2),
                'transactions' => (int) ($rows[$mysqlDay]->transactions ?? 0),
            ];
        }

        return $out;
    }

    private function branchSplit(array $branchIds, CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        if (count($branchIds) <= 1) {
            return [];
        }

        $rows = Sale::query()
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', $filters['status'] ?? 'active')
            ->whereBetween('sales.created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->selectRaw('sales.branch_id, branches.name, coalesce(sum(sales.total),0) as revenue, count(*) as transactions')
            ->groupBy('sales.branch_id', 'branches.name')
            ->orderByDesc('revenue')
            ->get();

        $grandTotal = (float) $rows->sum('revenue');

        return $rows
            ->map(fn ($row) => [
                'id' => (int) $row->branch_id,
                'name' => $row->name,
                'revenue' => round((float) $row->revenue, 2),
                'transactions' => (int) $row->transactions,
                'share' => $grandTotal > 0 ? round(((float) $row->revenue / $grandTotal) * 100, 1) : 0,
            ])
            ->all();
    }

    private function recentSales(array $branchIds, CarbonInterface $start, CarbonInterface $end, int $limit, ?int $scopeUserId = null, array $filters = []): array
    {
        return Sale::query()
            ->with(['branch:id,name', 'user:id,name', 'customer:id,name'])
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$start, $end])
            ->when($scopeUserId, fn ($q) => $q->where('user_id', $scopeUserId))
            ->when($filters['product'] ?? null, fn ($q, $productId) => $this->productSoldFilter($q, (int) $productId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_no' => $sale->sale_no,
                'status' => $sale->status,
                'payment_status' => $sale->payment_status,
                'total' => (float) $sale->total,
                'amount_paid' => (float) $sale->amount_paid,
                'created_at' => $sale->created_at?->toISOString(),
                'branch' => $sale->branch?->name,
                'user' => $sale->user?->name,
                'customer' => $sale->customer?->name,
            ])
            ->all();
    }

    private function topProducts(array $branchIds, CarbonInterface $start, CarbonInterface $end, int $limit, array $filters = []): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', $filters['status'] ?? 'active')
            ->whereBetween('sales.created_at', [$start, $end])
            ->when($filters['product'] ?? null, fn ($q, $productId) => $q->where('sale_items.product_id', (int) $productId))
            ->selectRaw('products.name, products.sku, sum(sale_items.quantity) as qty, coalesce(sum(sale_items.line_total),0) as revenue')
            ->groupBy('products.name', 'products.sku')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'sku' => $row->sku,
                'qty' => round((float) $row->qty, 1),
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    private function locations($user): array
    {
        if (! $user->seesAllLocations()) {
            return [];
        }

        return Branch::select('id', 'name', 'type')
            ->where('is_active', true)
            ->orderByRaw("case when type = 'godown' then 0 else 1 end, name")
            ->get()
            ->map(fn ($branch) => [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'type' => $branch->type,
            ])
            ->all();
    }

    private function alerts(array $branchIds, bool $withTier2Tier3 = true): array
    {
        $lowRows = Stock::query()
            ->join('products', 'products.id', '=', 'stock.product_id')
            ->join('branches', 'branches.id', '=', 'stock.branch_id')
            ->whereIn('stock.branch_id', $branchIds)
            ->where('products.is_active', true)
            ->whereColumn('stock.quantity', '<=', 'products.reorder_level')
            ->select(
                'products.id as product_id',
                'products.name',
                'products.sku',
                'products.reorder_level',
                'stock.branch_id',
                'stock.quantity',
                'branches.name as branch_name',
                'branches.type as branch_type',
            )
            ->orderBy('stock.quantity')
            ->get();

        $product_alerts = $lowRows
            ->map(fn (object $row): array => [
                'id' => (int) $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'quantity' => round((float) $row->quantity, 1),
                'reorder_level' => (int) $row->reorder_level,
                'location_id' => (int) $row->branch_id,
                'location_name' => $row->branch_name,
                'location_type' => $row->branch_type,
            ])
            ->values()
            ->all();

        $godown_alerts = $withTier2Tier3
            ? $lowRows
                ->where('branch_type', 'godown')
                ->map(fn (object $row): array => [
                    'godown_id' => (int) $row->branch_id,
                    'godown_name' => $row->branch_name,
                    'product_name' => $row->name,
                    'sku' => $row->sku,
                    'quantity' => round((float) $row->quantity, 1),
                    'reorder_level' => (int) $row->reorder_level,
                ])
                ->values()
                ->all()
            : [];

        $category_alerts = [];
        if ($withTier2Tier3) {
            $category_alerts = DB::table('stock')
                ->join('products', 'products.id', '=', 'stock.product_id')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->join('branches', 'branches.id', '=', 'stock.branch_id')
                ->join('category_thresholds', function ($join) {
                    $join->on('category_thresholds.category_id', '=', 'categories.id')
                        ->on('category_thresholds.branch_id', '=', 'stock.branch_id');
                })
                ->whereIn('stock.branch_id', $branchIds)
                ->where('products.is_active', true)
                ->selectRaw('categories.name as category, stock.branch_id as location_id, branches.name as location_name, branches.type as location_type, sum(stock.quantity) as quantity, category_thresholds.normal_capacity as threshold')
                ->groupBy('categories.name', 'stock.branch_id', 'branches.name', 'branches.type', 'category_thresholds.normal_capacity')
                ->havingRaw('sum(stock.quantity) <= category_thresholds.normal_capacity')
                ->get()
                ->map(fn (object $row): array => [
                    'category' => $row->category,
                    'location_id' => (int) $row->location_id,
                    'location_name' => $row->location_name,
                    'location_type' => $row->location_type,
                    'quantity' => round((float) $row->quantity, 1),
                    'threshold' => (int) $row->threshold,
                    'percent_of_normal' => (int) $row->threshold > 0 ? round(((float) $row->quantity / (float) $row->threshold) * 100) : 0,
                ])
                ->sortBy('percent_of_normal')
                ->values()
                ->all();
        }

        return [
            'product_alerts' => $product_alerts,
            'category_alerts' => $category_alerts,
            'godown_alerts' => $godown_alerts,
            'out_of_stock' => (int) Stock::query()->whereIn('branch_id', $branchIds)->where('quantity', '<=', 0)->distinct('product_id')->count('product_id'),
            'parked' => (int) ParkedSale::whereIn('branch_id', $branchIds)->whereNull('recalled_at')->count(),
            'open_shifts' => (int) Shift::open()->whereIn('branch_id', $branchIds)->count(),
            'active_layaways' => (int) Layaway::whereIn('branch_id', $branchIds)->where('status', 'active')->where('balance', '>', 0)->count(),
            'pending_stock_requests' => (int) RestockRequest::pending()->whereIn('branch_id', $branchIds)->count(),
        ];
    }

    private function customerHighlights(array $branchIds, CarbonInterface $start, CarbonInterface $end): array
    {
        $layaways = Layaway::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->where('balance', '>', 0);

        $buyerIds = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id');

        $returning = Customer::query()
            ->whereIn('id', $buyerIds->all())
            ->where('created_at', '<', $start)
            ->count();

        $walkInOrders = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('customer_id')
            ->count();

        return [
            'credit_outstanding' => round((float) Customer::whereIn('branch_id', $branchIds)->where('customer_type', 'credit')->where('credit_balance', '>', 0)->sum('credit_balance'), 2),
            'deposits_held' => round((float) Customer::whereIn('branch_id', $branchIds)->sum('deposit_balance'), 2),
            'new_customers' => (int) Customer::whereIn('branch_id', $branchIds)->whereBetween('created_at', [$start, $end])->count(),
            'returning_customers' => (int) $returning,
            'walk_in_orders' => (int) $walkInOrders,
            'active_layaways' => (int) (clone $layaways)->count(),
            'layaway_balance' => round((float) (clone $layaways)->sum('balance'), 2),
            'top_customers' => Customer::query()
                ->whereIn('branch_id', $branchIds)
                ->where('is_active', true)
                ->orderByDesc('lifetime_spend')
                ->limit(5)
                ->get(['id', 'name', 'customer_type', 'loyalty_tier', 'loyalty_points', 'lifetime_spend'])
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'customer_type' => $customer->customer_type,
                    'loyalty_tier' => $customer->loyalty_tier,
                    'loyalty_points' => $customer->loyalty_points,
                    'lifetime_spend' => round((float) $customer->lifetime_spend, 2),
                ])
                ->all(),
        ];
    }

    private function recentActivity(array $branchIds, int $limit): array
    {
        return Activity::query()
            ->with('causer:id,name')
            ->whereIn('branch_id', $branchIds)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'user' => $activity->causer?->name ?? 'System',
                'branch_id' => $activity->branch_id,
                'created_at' => $activity->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Finance sees the activity feed scoped to financial event types only —
     * never user logins, product edits, or other operational events.
     */
    private function recentFinancialActivity(array $branchIds, int $limit): array
    {
        return Activity::query()
            ->with('causer:id,name')
            ->whereIn('event', ['sale', 'refund', 'refund_requested', 'sale_voided', 'credit_payment', 'deposit', 'loyalty_adjust', 'shift_closed', 'shift_reconciled', 'cash_movement'])
            ->whereIn('branch_id', $branchIds)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'user' => $activity->causer?->name ?? 'System',
                'branch_id' => $activity->branch_id,
                'created_at' => $activity->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Finance-facing positions surfaced as dashboard widgets: pending refunds,
     * recent / flagged voids, cash drawer variances, cash-out approvals, and the
     * AR/AP balance summary.
     */
    private function financePositions(array $branchIds): array
    {
        $threshold = (float) Setting::number('finance.variance_threshold', 5000);

        $variances = Shift::query()
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('closed_at')
            ->whereRaw('abs(difference) > ?', [$threshold])
            ->whereDate('closed_at', today())
            ->count();

        return [
            'pending_refunds' => (int) RefundRequest::pending()->whereIn('branch_id', $branchIds)->count(),
            'voids_24h' => (int) Sale::whereIn('branch_id', $branchIds)->where('status', 'voided')->where('voided_at', '>=', now()->subDay())->count(),
            'flagged_voids_24h' => (int) Sale::whereIn('branch_id', $branchIds)->where('status', 'voided')->where('is_void_flagged', true)->where('voided_at', '>=', now()->subDay())->count(),
            'cash_variances_today' => $variances,
            'pending_cash_outs' => (int) CashMovement::pending()->whereIn('branch_id', $branchIds)->count(),
            'receivables' => round((float) Customer::whereIn('branch_id', $branchIds)->where('customer_type', 'credit')->where('credit_balance', '>', 0)->sum('credit_balance'), 2),
            'payables' => round((float) SupplierInvoice::whereIn('branch_id', $branchIds)->outstanding()->get()->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount), 2),
        ];
    }

    /**
     * Per-stage order counts visible to the requesting user's scoped locations.
     * Feeds the pipeline strip on the dashboard: each visible stage lights up
     * with a count that links through to the filtered /orders list.
     */
    private function orderPipeline(array $locationIds): array
    {
        $stages = [];
        foreach (BranchOrder::PIPELINE_STAGES as $stage) {
            $stages[$stage] = BranchOrder::where('status', $stage)
                ->where(fn ($q) => $q->whereIn('branch_id', $locationIds)->orWhereIn('godown_id', $locationIds))
                ->count();
        }

        return $stages;
    }

    /**
     * Stock value across the user's locations: sum over batches of
     * quantity × unit_cost — the same figure the stock valuation report and
     * StockService::averageUnitCost() are built on.
     */
    private function stockValue(array $locationIds): array
    {
        $rows = DB::table('stock')
            ->join('branches', 'branches.id', '=', 'stock.branch_id')
            ->leftJoin('stock_batches', 'stock_batches.stock_id', '=', 'stock.id')
            ->whereIn('stock.branch_id', $locationIds)
            ->where('stock_batches.quantity', '>', 0)
            ->selectRaw('stock.branch_id, branches.name as branch_name, branches.type as branch_type, coalesce(sum(stock_batches.quantity * stock_batches.unit_cost),0) as total_value')
            ->groupBy('stock.branch_id', 'branches.name', 'branches.type')
            ->orderBy('branch_name')
            ->get();

        $total = (float) $rows->sum('total_value');

        return [
            'total' => round($total, 2),
            'by_location' => $rows->map(fn ($r) => [
                'id' => (int) $r->branch_id,
                'name' => $r->branch_name,
                'type' => $r->branch_type,
                'value' => round((float) $r->total_value, 2),
            ])->values()->all(),
        ];
    }

    /**
     * Restrict a sales query to rows that contained the given product. Uses
     * an exists subquery so aggregations keep their grouping semantics.
     */
    private function productSoldFilter($query, int $productId): void
    {
        $query->whereExists(function ($sub) use ($productId) {
            $sub->selectRaw('1')
                ->from('sale_items')
                ->whereColumn('sale_items.sale_id', 'sales.id')
                ->where('sale_items.product_id', $productId);
        });
    }

    private function filterProducts(array $locationIds): array
    {
        return Product::query()
            ->where('is_active', true)
            ->whereHas('stock', fn ($q) => $q->whereIn('branch_id', $locationIds))
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }
}
