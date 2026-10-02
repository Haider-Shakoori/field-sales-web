<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesmanRequest;
use App\Http\Requests\UpdateSalesmanRequest;
use App\Models\Collection;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CustomerBalanceService;
use App\Services\OperationalAlertService;
use App\Services\TerritoryAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesmanController extends Controller
{
    public function index(Request $request, TerritoryAccessService $access): View
    {
        Gate::authorize('viewAny', Salesman::class);

        return view('admin.salesmen.index', [
            'salesmen' => Salesman::with(['user.branch', 'devices', 'assignments' => fn ($query) => $query->current()->with(['branch', 'supervisor'])])
                ->when($access->salesmanIds($request->user()), fn ($query, $ids) => $query->whereIn('id', $ids))
                ->orderBy('employee_code')
                ->paginate(min(100, max(10, request()->integer('per_page', 25))))->withQueryString(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Salesman::class);

        return view('admin.salesmen.create', [
            'users' => User::whereDoesntHave('salesman')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreSalesmanRequest $request, AuditLogger $audit): RedirectResponse
    {
        $salesman = Salesman::create($request->validated());

        $audit->record('salesman.created', $salesman, [], $this->auditValues($salesman));

        return redirect()
            ->route('admin.salesmen.index')
            ->with('status', 'Salesman profile created.');
    }

    public function show(
        Salesman $salesman,
        CustomerBalanceService $balances,
    ): View {
        Gate::authorize('view', $salesman);

        $referredCustomers = $salesman->referredCustomers()
            ->with(['territory', 'assignedSalesman'])
            ->withCount(['orders', 'collections', 'visits'])
            ->orderBy('name')
            ->paginate(
                min(100, max(12, request()->integer('referrals_per_page', 24))),
                ['*'],
                'referrals_page',
            )
            ->withQueryString();

        $customerIds = $salesman->referredCustomers()->select('customers.id');

        return view('admin.salesmen.show', [
            'salesman' => $salesman->load([
                'user.branch',
                'devices' => fn ($query) => $query->latest('registered_at'),
                'assignments' => fn ($query) => $query->with(['branch', 'supervisor'])->latest('effective_from'),
            ]),
            'referredCustomers' => $referredCustomers,
            'referralBalances' => collect(
                $balances->forCustomers($referredCustomers->getCollection())
            )->keyBy('customer_id'),
            'referralSummary' => [
                'customers' => $salesman->referredCustomers()->count(),
                'active_customers' => $salesman->referredCustomers()->active()->count(),
                'orders' => Order::query()
                    ->whereIn('customer_id', clone $customerIds)
                    ->count(),
                'collections' => Collection::query()
                    ->whereIn('customer_id', clone $customerIds)
                    ->count(),
                'visits' => CustomerVisit::query()
                    ->whereIn('customer_id', clone $customerIds)
                    ->count(),
            ],
        ]);
    }

    public function notify(
        Request $request,
        Salesman $salesman,
        OperationalAlertService $alerts,
    ): RedirectResponse {
        Gate::authorize('view', $salesman);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $salesman->loadMissing('user');
        $alerts->nudge($request->user(), $salesman, trim($validated['message']));

        return back()->with('status', 'Notification sent to '.$salesman->full_name.'.');
    }

    public function edit(Salesman $salesman): View
    {
        Gate::authorize('update', $salesman);

        return view('admin.salesmen.edit', compact('salesman'));
    }

    public function update(
        UpdateSalesmanRequest $request,
        Salesman $salesman,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($salesman);
        $salesman->update($request->validated());

        $audit->record('salesman.updated', $salesman, $before, $this->auditValues($salesman));

        return redirect()
            ->route('admin.salesmen.show', $salesman)
            ->with('status', 'Salesman profile updated.');
    }

    public function destroy(Salesman $salesman, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $salesman);

        if ($salesman->devices()->exists() || $salesman->assignments()->exists()) {
            throw ValidationException::withMessages([
                'salesman' => 'This salesman has device or assignment history. Deactivate the profile instead.',
            ]);
        }

        $before = $this->auditValues($salesman);
        $audit->record('salesman.deleted', $salesman, $before);
        $salesman->delete();

        return redirect()
            ->route('admin.salesmen.index')
            ->with('status', 'Salesman profile deleted.');
    }

    private function auditValues(Salesman $salesman): array
    {
        return [
            'user_id' => $salesman->user_id,
            'employee_code' => $salesman->employee_code,
            'first_name' => $salesman->first_name,
            'last_name' => $salesman->last_name,
            'is_active' => $salesman->is_active,
        ];
    }
}
