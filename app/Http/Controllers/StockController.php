<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\RestockRequest;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('stock.view'), 403);

        $locationIds = $user->scopedLocationIds();
        $defaultBranch = $user->seesAllLocations() ? null : ($user->branch_id ?? ($locationIds[0] ?? null));

        $query = Stock::query()
            ->whereIn('branch_id', $locationIds)
            ->with(['product:id,name,sku,barcode,reorder_level,unit_id', 'variant:id,name'])
            ->withCount([
                'batches as expiring_qty' => fn ($q) => $q
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                    ->where('quantity', '>', 0),
            ])
            ->when($request->filled('branch_id') && in_array((int) $request->query('branch_id'), $locationIds, true), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when(! $request->filled('branch_id') && $defaultBranch, fn ($q) => $q->where('branch_id', $defaultBranch))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$request->search}%")
                ->orWhere('sku', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%")));

        if ($request->filled('alert_type')) {
            match ($request->alert_type) {
                'low' => $query->join('products', 'products.id', '=', 'stock.product_id')
                    ->where('products.is_active', true)
                    ->where(function ($q) {
                        $q->whereColumn('stock.quantity', '<=', 'products.reorder_level')
                            ->orWhere('stock.quantity', '<=', 0);
                    }),
                'out' => $query->where('stock.quantity', '<=', 0),
                'expiring' => $query->whereHas('batches', fn ($q) => $q->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                    ->where('quantity', '>', 0)),
                default => null,
            };
        }

        $rows = $query->with('branch:id,name,type')->latest('updated_at')->paginate(30)->withQueryString();

        return Inertia::render('stock/index', [
            'stock' => $rows,
            'restocks' => $user->can('stock.restock.approve')
                ? RestockRequest::query()
                    ->with([
                        'product:id,name,sku',
                        'productVariant:id,name',
                        'branch:id,name,type',
                        'sourceBranch:id,name,type',
                        'requester:id,name',
                    ])
                    ->whereIn('branch_id', $locationIds)
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                : collect(),
            'canApprove' => $user->can('stock.restock.approve'),
            'branches' => Branch::select('id', 'name')
                ->whereIn('id', $locationIds)
                ->orderBy('name')
                ->get(),
            'defaultBranch' => $defaultBranch,
            'filters' => $request->only(['branch_id', 'alert_type', 'search', 'view']),
        ]);
    }
}
