<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
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

    private function parseFrom(Request $request): CarbonInterface
    {
        return Carbon::parse($request->filled('from') ? $request->from : now()->toDateString())->startOfDay();
    }

    private function parseTo(Request $request): CarbonInterface
    {
        return Carbon::parse($request->filled('to') ? $request->to : now()->toDateString())->endOfDay();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $branchIds = $user->scopedLocationIds();
        $from = $this->parseFrom($request);
        $to = $this->parseTo($request);

        $revenue = (float) Sale::whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');

        $transactions = (int) Sale::whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $cogs = (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'active')
            ->whereBetween('sales.created_at', [$from, $to])
            ->sum('sale_items.cost_price');

        $grossProfit = $revenue - $cogs;

        $refunds = Sale::whereIn('branch_id', $branchIds)
            ->where('status', 'returned')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('count(*) as count, coalesce(sum(total),0) as amount')
            ->first();

        $paymentSplit = Payment::join('sales', 'sales.id', '=', 'payments.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'active')
            ->whereBetween('payments.created_at', [$from, $to])
            ->selectRaw('payments.method as method, coalesce(sum(payments.amount),0) as total')
            ->groupBy('payments.method')
            ->pluck('total', 'method');

        $grandTotal = (float) $paymentSplit->sum();

        $split = collect(self::PAYMENT_LABELS)
            ->map(fn (string $label, string $method) => [
                'method' => $method,
                'label' => $label,
                'amount' => round((float) ($paymentSplit[$method] ?? 0), 2),
                'percentage' => $grandTotal > 0 ? round(((float) ($paymentSplit[$method] ?? 0) / $grandTotal) * 100, 1) : 0,
            ])
            ->reject(fn (array $row) => $row['amount'] <= 0)
            ->values()
            ->all();

        $daily = Sale::whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('date(created_at) as day, coalesce(sum(total),0) as revenue, count(*) as transactions')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'revenue' => round((float) $row->revenue, 2),
                'transactions' => (int) $row->transactions,
            ])
            ->all();

        return Inertia::render('finance/reports', [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'summary' => [
                'revenue' => round($revenue, 2),
                'transactions' => $transactions,
                'cogs' => round($cogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0,
                'refunds_count' => (int) $refunds?->count,
                'refunds_amount' => round((float) $refunds?->amount, 2),
            ],
            'can_view_margin' => $user->can('view profit margins'),
            'can_export' => $user->can('export sales reports'),
            'payment_split' => $split,
            'daily' => $daily,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $branchIds = $user->scopedLocationIds();
        $from = $this->parseFrom($request);
        $to = $this->parseTo($request);

        $sales = Sale::with(['branch:id,name,currency', 'user:id,name', 'customer:id,name', 'payments'])
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $stream = $this->csvStream(function () use ($sales) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Sale No', 'Date', 'Branch', 'Cashier', 'Customer', 'Status', 'Total', 'Amount Paid', 'Payment Method', 'Reference']);
            foreach ($sales as $sale) {
                $payments = $sale->payments;
                if ($payments->isEmpty()) {
                    fputcsv($handle, [$sale->sale_no, $sale->created_at?->toDateTimeString(), $sale->branch?->name, $sale->user?->name, $sale->customer?->name, $sale->status, $sale->total, $sale->amount_paid, '', '']);

                    continue;
                }
                foreach ($payments as $payment) {
                    fputcsv($handle, [$sale->sale_no, $sale->created_at?->toDateTimeString(), $sale->branch?->name, $sale->user?->name, $sale->customer?->name, $sale->status, $sale->total, $sale->amount_paid, $payment->method, $payment->reference]);
                }
            }
            fclose($handle);
        });

        $stream->setStatusCode(200);

        return $stream;
    }

    private function csvStream(callable $writer, string $filename = 'sales.csv'): StreamedResponse
    {
        return HttpResponse::stream($writer, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
