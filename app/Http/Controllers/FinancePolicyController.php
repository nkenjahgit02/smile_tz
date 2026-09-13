<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancePolicyController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('finance/policy', [
            'discount_max_percent' => (float) Setting::number('finance.discount_max_percent', 5),
            'discount_max_amount' => (float) Setting::number('finance.discount_max_amount', 0),
            'variance_threshold' => (float) Setting::number('finance.variance_threshold', 5000),
            'refund_auto_approve_limit' => (float) Setting::number('finance.refund_auto_approve_limit', 50000),
            'void_flag_amount' => (float) Setting::number('finance.void_flag_amount', 200000),
            'void_flag_daily_count' => (int) Setting::number('finance.void_flag_daily_count', 3),
            'overdue_days_receivable' => (int) Setting::number('finance.overdue_days_receivable', 30),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'discount_max_percent' => ['required', 'numeric', 'between:0,100'],
            'discount_max_amount' => ['required', 'numeric', 'min:0'],
            'variance_threshold' => ['required', 'numeric', 'min:0'],
            'refund_auto_approve_limit' => ['required', 'numeric', 'min:0'],
            'void_flag_amount' => ['required', 'numeric', 'min:0'],
            'void_flag_daily_count' => ['required', 'integer', 'min:1'],
            'overdue_days_receivable' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        Setting::set('finance.discount_max_percent', (string) ((float) $validated['discount_max_percent']), 'finance');
        Setting::set('finance.discount_max_amount', (string) ((float) $validated['discount_max_amount']), 'finance');
        Setting::set('finance.variance_threshold', (string) ((float) $validated['variance_threshold']), 'finance');
        Setting::set('finance.refund_auto_approve_limit', (string) ((float) $validated['refund_auto_approve_limit']), 'finance');
        Setting::set('finance.void_flag_amount', (string) ((float) $validated['void_flag_amount']), 'finance');
        Setting::set('finance.void_flag_daily_count', (string) ((int) $validated['void_flag_daily_count']), 'finance');
        Setting::set('finance.overdue_days_receivable', (string) ((int) $validated['overdue_days_receivable']), 'finance');

        return back()->with('success', 'Finance policy saved.');
    }
}
