<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Salesman;
use App\Models\Territory;
use App\Services\AuditLogger;
use App\Services\CustomerBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerBalanceService $balances): View
    {
        Gate::authorize('viewAny', Customer::class);
        $search = trim((string) $request->string('search'));

        $customers = Customer::with(['branch', 'territory', 'priceList'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'balanceRows' => collect($balances->forCustomers($customers->getCollection()))->keyBy('customer_id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('admin.customers.create', $this->formData());
    }

    public function store(StoreCustomerRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $customer = Customer::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'credit_currency' => strtoupper($validated['credit_currency'] ?? 'AFN'),
            'credit_terms_days' => $validated['credit_terms_days'] ?? 30,
            'created_by' => $request->user()->id,
        ]);

        $audit->record('customer.created', $customer, [], $this->auditValues($customer));

        return redirect()->route('admin.customers.show', $customer)->with('status', __('Customer created.'));
    }

    public function show(Request $request, Customer $customer, CustomerBalanceService $balances): View
    {
        Gate::authorize('view', $customer);
        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        return view('admin.customers.show', [
            'customer' => $customer->load(['branch', 'territory', 'priceList', 'creator', 'routeMemberships.route', 'callActivities.user', 'followUps.assignedSalesman']),
            'creditSnapshot' => $balances->snapshot($customer, $customer->credit_currency ?? 'AFN'),
            'aging' => $balances->aging($customer),
            'salesmen' => Salesman::active()->orderBy('employee_code')->get(),
            'timezone' => $timezone,
            'defaultFollowUpAt' => now($timezone)->addDay()->format('Y-m-d\TH:i'),
        ]);
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('admin.customers.edit', [...$this->formData(), 'customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, AuditLogger $audit): RedirectResponse
    {
        $before = $this->auditValues($customer);
        $validated = $request->validated();

        $customer->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'credit_currency' => strtoupper(
                $validated['credit_currency'] ?? $customer->credit_currency ?? 'AFN'
            ),
            'credit_terms_days' => $validated['credit_terms_days']
                ?? $customer->credit_terms_days
                ?? 30,
        ]);

        $audit->record('customer.updated', $customer, $before, $this->auditValues($customer));

        return redirect()->route('admin.customers.show', $customer)->with('status', __('Customer updated.'));
    }

    public function destroy(Customer $customer, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->routeMemberships()->exists()) {
            throw ValidationException::withMessages(['customer' => 'This customer is assigned to one or more routes. Remove route membership or deactivate the customer.']);
        }

        $before = $this->auditValues($customer);
        $audit->record('customer.deleted', $customer, $before);
        $customer->delete();

        return redirect()->route('admin.customers.index')->with('status', __('Customer deleted.'));
    }

    private function formData(): array
    {
        return [
            'branches' => Branch::active()->orderBy('name')->get(),
            'territories' => Territory::active()->with('branch')->orderBy('name')->get(),
            'priceLists' => PriceList::active()->effectiveOn()->orderBy('name')->get(),
        ];
    }

    private function auditValues(Customer $customer): array
    {
        return [
            'branch_id' => $customer->branch_id, 'territory_id' => $customer->territory_id, 'price_list_id' => $customer->price_list_id,
            'credit_limit' => $customer->credit_limit, 'credit_currency' => $customer->credit_currency, 'credit_terms_days' => $customer->credit_terms_days,
            'code' => $customer->code, 'name' => $customer->name, 'contact_person' => $customer->contact_person, 'phone' => $customer->phone,
            'alternate_phone' => $customer->alternate_phone, 'email' => $customer->email, 'address' => $customer->address,
            'latitude' => $customer->latitude, 'longitude' => $customer->longitude, 'geofence_radius_meters' => $customer->geofence_radius_meters,
            'offline_uuid' => $customer->offline_uuid, 'is_active' => $customer->is_active,
        ];
    }
}
