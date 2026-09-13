<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $branchIds = $user->scopedLocationIds();
        $rate = (float) Setting::number('tax.vat_rate', 18);
        $enabled = Setting::bool('tax.vat_enabled', false);

        $driver = DB::getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $months = Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("{$monthExpr} as ym, coalesce(sum(total),0) as revenue")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->ym,
                'revenue' => round((float) $row->revenue, 2),
                'vat' => round((float) $row->revenue * ($rate / (100 + $rate)), 2),
            ])
            ->all();

        return Inertia::render('finance/tax', [
            'vat_enabled' => $enabled,
            'vat_rate' => $rate,
            'months' => $months,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'vat_enabled' => ['nullable', 'boolean'],
            'vat_rate' => ['required', 'numeric', 'between:0,100'],
        ]);

        Setting::set('tax.vat_enabled', $validated['vat_enabled'] ?? false, 'finance');
        Setting::set('tax.vat_rate', (string) ((float) $validated['vat_rate']), 'finance');

        return back()->with('success', 'Tax settings updated.');
    }
}
