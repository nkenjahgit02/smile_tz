<?php

namespace App\Http\Controllers;

use App\Models\Layaway;
use App\Support\ActivityLogger;
use App\Support\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LayawayController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request): Response
    {
        return Inertia::render('layaways/index', [
            'layaways' => Layaway::query()
                ->with(['customer:id,name,phone', 'branch:id,name', 'sale:id,sale_no'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function show(Request $request, Layaway $layaway): Response
    {
        $layaway->load(['customer', 'branch', 'sale.items.product', 'payments.receiver:id,name', 'creator:id,name']);

        return Inertia::render('layaways/show', [
            'layaway' => $layaway,
            'can_manage' => $request->user()->can('sales.layaway'),
        ]);
    }

    public function pay(Request $request, Layaway $layaway)
    {
        abort_unless($request->user()->can('sales.layaway'), 403);

        $user = $request->user();
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'lte:'.$layaway->balance],
            'method' => ['required', 'in:cash,card,mobile,bank_transfer'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->sales->payInstallment($layaway, $validated['amount'], $validated['method'], $validated['reference'] ?? null, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        ActivityLogger::log("Layaway {$layaway->id} installment of {$validated['amount']}", $layaway, 'layaway');

        return back()->with('success', 'Installment recorded.');
    }

    public function cancel(Request $request, Layaway $layaway)
    {
        abort_unless($request->user()->can('sales.layaway'), 403);
        abort_unless($layaway->status === 'active', 403, 'Only active layaways can be cancelled.');

        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];

        $layaway->update(['status' => 'cancelled']);

        ActivityLogger::log("Cancelled layaway {$layaway->id}: {$reason}", $layaway, 'layaway');

        return back()->with('success', 'Layaway cancelled.');
    }
}
