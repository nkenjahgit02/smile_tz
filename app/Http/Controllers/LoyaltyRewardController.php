<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyReward;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyRewardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('loyalty/index', [
            'rewards' => LoyaltyReward::query()
                ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->boolean('status')))
                ->orderBy('points_cost')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('customers.loyalty'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:points,percentage,fixed'],
            'value' => ['required', 'numeric', 'gt:0'],
            'min_tier' => ['required', 'in:bronze,silver,gold,platinum'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $reward = LoyaltyReward::create([
            'name' => $validated['name'],
            'points_cost' => $validated['points_cost'],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_tier' => $validated['min_tier'],
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        ActivityLogger::log("Created loyalty reward {$reward->name}", $reward, 'loyalty_reward');

        return back()->with('success', 'Reward created.');
    }

    public function update(Request $request, LoyaltyReward $reward)
    {
        abort_unless($request->user()->can('customers.loyalty'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:points,percentage,fixed'],
            'value' => ['required', 'numeric', 'gt:0'],
            'min_tier' => ['required', 'in:bronze,silver,gold,platinum'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $reward->update([
            'name' => $validated['name'],
            'points_cost' => $validated['points_cost'],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_tier' => $validated['min_tier'],
            'is_active' => $validated['is_active'] ?? $reward->is_active,
        ]);

        ActivityLogger::log("Updated loyalty reward {$reward->name}", $reward, 'loyalty_reward');

        return back()->with('success', 'Reward updated.');
    }

    public function destroy(Request $request, LoyaltyReward $reward)
    {
        abort_unless($request->user()->can('customers.loyalty'), 403);

        ActivityLogger::log("Deleted loyalty reward {$reward->name}");
        $reward->delete();

        return back()->with('success', 'Reward deleted.');
    }

    /**
     * JSON list for the POS terminal (active rewards only).
     */
    public function options(): JsonResponse
    {
        $rewards = LoyaltyReward::active()
            ->orderBy('points_cost')
            ->get(['id', 'name', 'points_cost', 'type', 'value', 'min_tier']);

        return response()->json($rewards);
    }
}
