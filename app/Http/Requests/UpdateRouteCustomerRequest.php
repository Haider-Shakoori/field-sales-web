<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Route;
use App\Models\RouteCustomer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRouteCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('route_customer')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'route_id' => ['required', Rule::exists('routes', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'visit_order' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenantId = TenantContext::currentId();
            $routeId = $this->input('route_id');
            $customerId = $this->input('customer_id');
            $visitOrder = $this->input('visit_order');
            $effectiveFrom = $this->input('effective_from');
            $effectiveTo = $this->input('effective_to');
            $currentId = $this->route('route_customer')->id ?? null;

            // Validate customer territory is compatible with route territory
            if ($routeId && $customerId) {
                $route = Route::where('tenant_id', $tenantId)->where('id', $routeId)->first();
                $customer = Customer::where('tenant_id', $tenantId)->where('id', $customerId)->first();

                if ($route && $customer && $route->territory_id !== $customer->territory_id) {
                    $validator->errors()->add('customer_id', 'The customer must belong to the same territory as the route.');
                }
            }

            // Prevent duplicate active route membership for the same customer
            if ($customerId) {
                $duplicate = RouteCustomer::where('tenant_id', $tenantId)
                    ->where('customer_id', $customerId)
                    ->where('route_id', '!=', $routeId)
                    ->where(function ($query) use ($effectiveFrom) {
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $effectiveFrom);
                    })
                    ->where('effective_from', '<=', $effectiveTo ?? '9999-12-31')
                    ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('customer_id', 'The customer already has an active assignment to another route during this period.');
                }
            }

            // Prevent duplicate visit_order for the same route
            if ($routeId && $visitOrder) {
                $duplicate = RouteCustomer::where('tenant_id', $tenantId)
                    ->where('route_id', $routeId)
                    ->where('visit_order', $visitOrder)
                    ->where(function ($query) use ($effectiveFrom) {
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $effectiveFrom);
                    })
                    ->where('effective_from', '<=', $effectiveTo ?? '9999-12-31')
                    ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('visit_order', 'This visit order is already taken for the selected route during this period.');
                }
            }
        });
    }
}
