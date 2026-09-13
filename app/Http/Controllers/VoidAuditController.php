<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoidAuditController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $voids = Sale::query()
            ->with(['branch:id,name', 'voidedBy:id,name', 'user:id,name'])
            ->where('status', 'voided')
            ->whereNotNull('voided_at')
            ->whereIn('branch_id', $user->scopedLocationIds())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('voided_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('voided_at', '<=', $request->to))
            ->when($request->boolean('flagged'), fn ($q) => $q->where('is_void_flagged', true))
            ->when($request->filled('search'), fn ($q) => $q->where('sale_no', 'like', "%{$request->search}%"))
            ->orderByDesc('voided_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('finance/voids', [
            'voids' => $voids,
            'filters' => $request->only(['from', 'to', 'flagged', 'search']),
        ]);
    }
}
