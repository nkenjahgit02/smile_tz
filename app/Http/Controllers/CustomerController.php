<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\CustomerAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function index(Request $request): Response
    {
        return Inertia::render('customers/index', [
            'customers' => Customer::query()
                ->withCount(['sales', 'layaways'])
                ->whereIn('branch_id', $request->user()->scopedLocationIds())
                ->when($request->filled('search'), function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%")
                            ->orWhere('phone', 'like', "%{$request->search}%")
                            ->orWhere('customer_no', 'like', "%{$request->search}%");
                    });
                })
                ->when($request->filled('type'), fn ($q) => $q->where('customer_type', $request->type))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only('search', 'type'),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->assertCustomerInScope($request->user(), $customer);

        $customer->load(['transactions.creator:id,name']);

        return Inertia::render('customers/show', [
            'customer' => $customer,
            'transactions' => $customer->transactions()->latest()->limit(50)->get(),
            'can' => [
                'manage' => $request->user()->can('customers.manage'),
                'credit' => $request->user()->can('customers.credit'),
                'loyalty' => $request->user()->can('customers.loyalty'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('customers.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'customer_type' => ['required', 'in:walk-in,member,credit'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::create([
            'branch_id' => $request->user()->branch_id,
            'customer_no' => 'CUST-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'customer_type' => $validated['customer_type'],
            'credit_limit' => $request->user()->can('customers.credit')
                ? ($validated['credit_limit'] ?? 0)
                : 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        ActivityLogger::log("Created customer {$customer->customer_no}", $customer, 'customer');

        return back()->with('success', 'Customer created.');
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless($request->user()->can('customers.manage'), 403);
        $this->assertCustomerInScope($request->user(), $customer);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'customer_type' => ['required', 'in:walk-in,member,credit'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'loyalty_tier' => ['nullable', 'in:bronze,silver,gold,platinum'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'customer_type' => $validated['customer_type'],
            'credit_limit' => $request->user()->can('customers.credit')
                ? ($validated['credit_limit'] ?? $customer->credit_limit)
                : $customer->credit_limit,
            'is_active' => $validated['is_active'] ?? $customer->is_active,
            'notes' => $validated['notes'] ?? null,
        ]);

        ActivityLogger::log("Updated customer {$customer->customer_no}", $customer, 'customer');

        return back()->with('success', 'Customer updated.');
    }

    /**
     * JSON search endpoint for the POS terminal.
     */
    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:1']]);

        $customers = Customer::where('branch_id', $request->user()->branch_id)
            ->active()
            ->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->q}%")
                    ->orWhere('phone', 'like', "%{$request->q}%")
                    ->orWhere('customer_no', 'like', "%{$request->q}%");
            })
            ->select('id', 'customer_no', 'name', 'phone', 'customer_type', 'loyalty_tier', 'loyalty_points', 'deposit_balance', 'credit_balance', 'credit_limit')
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    /**
     * Receive a payment against a customer's credit balance (AR collection).
     */
    public function collectCredit(Request $request, Customer $customer)
    {
        abort_unless($request->user()->can('customers.credit'), 403);
        $this->assertCustomerInScope($request->user(), $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,card,mobile,bank_transfer,deposit'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->accounts->collectCredit(
                $customer,
                (float) $validated['amount'],
                $request->user()->branch_id,
                method: $validated['method'],
                description: isset($validated['reference']) && $validated['reference'] ? "Credit payment received ({$validated['method']}) — {$validated['reference']}" : null,
                actedBy: $request->user(),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        ActivityLogger::log("Collected {$validated['amount']} credit from {$customer->name}", $customer, 'credit_payment');

        return back()->with('success', 'Credit payment recorded.');
    }

    /**
     * Add funds to a customer's deposit / wallet balance.
     */
    public function deposit(Request $request, Customer $customer)
    {
        abort_unless($request->user()->can('customers.manage'), 403);
        $this->assertCustomerInScope($request->user(), $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->accounts->deposit(
            $customer,
            (float) $validated['amount'],
            $request->user()->branch_id,
            description: $validated['notes'] ?? 'Customer deposit',
            actedBy: $request->user(),
        );

        ActivityLogger::log("Deposited {$validated['amount']} for customer {$customer->name}", $customer, 'deposit');

        return back()->with('success', 'Deposit recorded.');
    }

    /**
     * Manually credit a customer's loyalty points (bonus / penalty).
     */
    public function adjustPoints(Request $request, Customer $customer)
    {
        abort_unless($request->user()->can('customers.loyalty'), 403);
        $this->assertCustomerInScope($request->user(), $customer);

        $validated = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->accounts->adjustPoints(
            $customer,
            (int) $validated['points'],
            $request->user()->branch_id,
            $validated['reason'],
            actedBy: $request->user(),
        );

        ActivityLogger::log("Adjusted {$validated['points']} pts for {$customer->name}", $customer, 'loyalty_adjust');

        return back()->with('success', 'Points updated.');
    }

    private function assertCustomerInScope(User $user, Customer $customer): void
    {
        abort_unless(in_array($customer->branch_id, $user->scopedLocationIds(), true), 403);
    }
}
