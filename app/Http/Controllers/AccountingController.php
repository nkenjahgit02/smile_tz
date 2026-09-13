<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    private function overdueDays(): int
    {
        return (int) Setting::number('finance.overdue_days_receivable', 30);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $locationIds = $user->scopedLocationIds();
        $overdueDays = $this->overdueDays();

        // Accounts Receivable: customer credit balances.
        $receivables = Customer::query()
            ->whereIn('branch_id', $locationIds)
            ->where('credit_balance', '>', 0)
            ->orderByDesc('credit_balance')
            ->limit(200)
            ->get()
            ->map(function (Customer $customer) use ($request, $overdueDays) {
                $lastActivity = $customer->transactions()
                    ->whereIn('type', ['credit_sale', 'credit_payment', 'credit_adjust'])
                    ->latest('id')
                    ->first();
                $overdue = $lastActivity ? $lastActivity->created_at->lt(now()->subDays($overdueDays)) : true;

                if ($overdue) {
                    $this->notifyReceivableOverdue($customer, $request->user());
                }

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'credit_balance' => round((float) $customer->credit_balance, 2),
                    'credit_limit' => round((float) $customer->credit_limit, 2),
                    'overdue' => $overdue,
                    'last_activity' => $lastActivity?->created_at?->toDateString(),
                ];
            })
            ->values()
            ->all();

        // Accounts Payable: outstanding supplier invoices.
        $invoices = SupplierInvoice::query()
            ->with(['supplier:id,name', 'branch:id,name'])
            ->whereIn('branch_id', $locationIds)
            ->outstanding()
            ->orderBy('due_date')
            ->limit(200)
            ->get()
            ->map(function (SupplierInvoice $invoice) use ($request) {
                $overdue = $invoice->due_date->lt(now()->toDateString());
                if ($overdue) {
                    $this->notifyInvoiceDue($invoice, $request->user());
                }

                return [
                    'id' => $invoice->id,
                    'supplier' => $invoice->supplier?->name,
                    'invoice_no' => $invoice->invoice_no,
                    'amount' => round((float) $invoice->amount, 2),
                    'paid_amount' => round((float) $invoice->paid_amount, 2),
                    'balance' => round((float) $invoice->amount - (float) $invoice->paid_amount, 2),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'status' => $invoice->status,
                    'overdue' => $overdue,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('finance/accounting', [
            'receivables_total' => round((float) collect($receivables)->sum('credit_balance'), 2),
            'payables_total' => round((float) collect($invoices)->sum('balance'), 2),
            'receivables' => $receivables,
            'invoices' => $invoices,
            'overdue_days' => $overdueDays,
            'filters' => $request->only('view'),
        ]);
    }

    public function payInvoice(Request $request, SupplierInvoice $invoice)
    {
        abort_unless($request->user()->can('manage supplier invoices'), 403);

        if (! in_array($invoice->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $payment = min((float) $validated['amount'], (float) $invoice->amount - (float) $invoice->paid_amount);
        $paid = round((float) $invoice->paid_amount + $payment, 2);

        $invoice->update([
            'paid_amount' => $paid,
            'status' => $paid >= (float) $invoice->amount ? 'paid' : 'partial',
        ]);

        ActivityLogger::log(
            "Recorded {$payment} payment against supplier invoice {$invoice->invoice_no}",
            $invoice,
            'cash_movement',
        );

        return back()->with('success', 'Invoice payment recorded.');
    }

    public function export(Request $request): StreamedResponse
    {
        $locationIds = $request->user()->scopedLocationIds();

        $receivables = Customer::query()
            ->whereIn('branch_id', $locationIds)
            ->where('credit_balance', '>', 0)
            ->orderBy('name')
            ->get();

        $invoices = SupplierInvoice::query()
            ->with('supplier:id,name')
            ->whereIn('branch_id', $locationIds)
            ->get();

        $stream = HttpResponse::stream(function () use ($receivables, $invoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Accounts Receivable (Customer Credit)', '']);
            fputcsv($handle, ['Customer', 'Outstanding Balance']);
            foreach ($receivables as $customer) {
                fputcsv($handle, [$customer->name, round((float) $customer->credit_balance, 2)]);
            }
            fputcsv($handle, []);
            fputcsv($handle, ['Accounts Payable (Supplier Invoices)', '']);
            fputcsv($handle, ['Supplier', 'Invoice No', 'Due Date', 'Balance']);
            foreach ($invoices as $invoice) {
                fputcsv($handle, [$invoice->supplier?->name, $invoice->invoice_no, $invoice->due_date?->toDateString(), round((float) $invoice->amount - (float) $invoice->paid_amount, 2)]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="accounting-export.csv"',
        ]);

        return $stream;
    }

    private function notifyReceivableOverdue(Customer $customer, User $initiator): void
    {
        $this->deliverOnce(
            "Customer {$customer->name} has an overdue credit balance of {$customer->credit_balance} (credit: {$customer->customer_no}).",
            '/finance/accounting?entity=AR-'.$customer->id,
            'receivable_overdue',
            $initiator,
        );
    }

    private function notifyInvoiceDue(SupplierInvoice $invoice, User $initiator): void
    {
        $balance = round((float) $invoice->amount - (float) $invoice->paid_amount, 2);
        $this->deliverOnce(
            "Supplier invoice {$invoice->invoice_no} ({$invoice->supplier?->name}) of {$balance} is due/overdue ({$invoice->due_date?->toDateString()}).",
            '/finance/accounting?entity=AP-'.$invoice->id,
            'invoice_due',
            $initiator,
        );
    }

    /**
     * Deliver a finance notification once per entity — never spam the drawer
     * with the same overdue item on every page load.
     */
    private function deliverOnce(string $message, string $link, string $type, User $initiator): void
    {
        // JSON stored columns escape slashes, so a raw LIKE on the URL path
        // breaks when the link contains path separators. Fetch recent rows
        // and compare the decoded data instead.
        $existing = DatabaseNotification::query()
            ->where('category', 'finance')
            ->where('finance_type', $type)
            ->where('created_at', '>=', now()->subDay())
            ->get(['id', 'data'])
            ->contains(function ($notification) use ($link) {
                $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);

                return ($data['link'] ?? null) === $link;
            });

        if ($existing) {
            return;
        }

        $recipients = User::role('Finance')->get();
        if ($recipients->isEmpty()) {
            return;
        }

        (new SystemNotification($message, $link, category: 'finance', target_role: 'Finance', type: $type))->deliver($recipients);
    }
}
