<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $branches = Branch::withCount('users')
            ->with('parent:id,name')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('branches/index', [
            'branches' => $branches,
            'godowns' => Branch::godowns()->active()->select('id', 'name', 'code')->orderBy('name')->get(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (($data['type'] ?? 'store') === 'godown') {
            $data['parent_id'] = null;
        }

        $branch = Branch::create($data);

        ActivityLogger::log("Created branch {$branch->name} ({$branch->code})", $branch, 'created', [], $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch created.')]);

        return back();
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $data = $request->validated();

        if ((int) ($data['parent_id'] ?? 0) === $branch->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A branch cannot be its own supplier.')]);

            return back();
        }

        if (($data['type'] ?? 'store') === 'godown') {
            $data['parent_id'] = null;
        }

        $old = $branch->only('name', 'code', 'address', 'phone', 'email', 'currency', 'is_active');
        $branch->update($data);

        ActivityLogger::log("Updated branch {$branch->name}", $branch, 'updated', [
            'old_values' => $old,
            'new_values' => $branch->only('name', 'code', 'address', 'phone', 'email', 'currency', 'is_active'),
        ], $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch updated.')]);

        return back();
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        if ($branch->users()->count() > 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a branch that has users.')]);

            return back();
        }

        ActivityLogger::log("Deleted branch {$branch->name}", $branch, 'deleted', [], $request);

        $branch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch deleted.')]);

        return back();
    }
}
