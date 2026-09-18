<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Territory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $search = trim((string) $request->string('search'));

        return view('admin.customers.index', [
            'customers' => Customer::with(['branch', 'territory'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($nested) use ($search): void {
                        $nested->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('admin.customers.create', $this->formData());
    }

    public function store(
        StoreCustomerRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        $customer = Customer::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()->id,
        ]);

        $audit->record('customer.created', $customer, [], $this->auditValues($customer));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);

        return view('admin.customers.show', [
            'customer' => $customer->load([
                'branch',
                'territory',
                'creator',
                'routeMemberships.route',
            ]),
        ]);
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('admin.customers.edit', [
            ...$this->formData(),
            'customer' => $customer,
        ]);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($customer);
        $validated = $request->validated();

        $customer->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
        ]);

        $audit->record('customer.updated', $customer, $before, $this->auditValues($customer));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->routeMemberships()->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'This customer is assigned to one or more routes. Remove route membership or deactivate the customer.',
            ]);
        }

        $before = $this->auditValues($customer);
        $audit->record('customer.deleted', $customer, $before);
        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('status', 'Customer deleted.');
    }

    private function formData(): array
    {
        return [
            'branches' => Branch::active()->orderBy('name')->get(),
            'territories' => Territory::active()->with('branch')->orderBy('name')->get(),
        ];
    }

    private function auditValues(Customer $customer): array
    {
        return [
            'branch_id' => $customer->branch_id,
            'territory_id' => $customer->territory_id,
            'code' => $customer->code,
            'name' => $customer->name,
            'contact_person' => $customer->contact_person,
            'phone' => $customer->phone,
            'alternate_phone' => $customer->alternate_phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'geofence_radius_meters' => $customer->geofence_radius_meters,
            'offline_uuid' => $customer->offline_uuid,
            'is_active' => $customer->is_active,
        ];
    }
}
