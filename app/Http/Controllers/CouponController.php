<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('coupons/index', [
            'coupons' => Coupon::query()
                ->when($request->filled('search'), fn ($q) => $q->where('code', 'like', "%{$request->search}%"))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('coupons.manage'), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'gt:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],
        ]);

        $coupon = Coupon::create([
            'code' => Str::upper($validated['code']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_spend' => $validated['min_spend'] ?? 0,
            'max_discount' => $validated['max_discount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_to' => $validated['valid_to'] ?? null,
        ]);

        ActivityLogger::log("Created coupon {$coupon->code}", $coupon, 'coupon');

        return back()->with('success', "Coupon {$coupon->code} created.");
    }

    public function update(Request $request, Coupon $coupon)
    {
        abort_unless($request->user()->can('coupons.manage'), 403);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'gt:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],
        ]);

        $coupon->update([
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_spend' => $validated['min_spend'] ?? 0,
            'max_discount' => $validated['max_discount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_to' => $validated['valid_to'] ?? [$coupon->valid_to, null],
        ]);

        ActivityLogger::log("Updated coupon {$coupon->code}", $coupon, 'coupon');

        return back()->with('success', "Coupon {$coupon->code} updated.");
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        abort_unless($request->user()->can('coupons.manage'), 403);

        ActivityLogger::log("Deleted coupon {$coupon->code}", $coupon, 'coupon');
        $coupon->delete();

        return back()->with('success', "Coupon {$coupon->code} deleted.");
    }

    /**
     * Live validation used by the POS terminal before completing checkout.
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $coupon = Coupon::where('code', Str::upper($request->code))->first();

        if (! $coupon || ! $coupon->isValidFor((float) $request->subtotal)) {
            return response()->json(['valid' => false]);
        }

        return response()->json([
            'valid' => true,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'discount' => $coupon->discountFor((float) $request->subtotal),
        ]);
    }
}
