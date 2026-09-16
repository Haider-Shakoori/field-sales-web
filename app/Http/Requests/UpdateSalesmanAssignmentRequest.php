<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Territory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesmanAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('salesman_assignment')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'salesman_id' => ['required', Rule::exists('salesmen', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'territory_id' => ['required', Rule::exists('territories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'route_id' => ['nullable', Rule::exists('routes', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'supervisor_id' => ['nullable', Rule::exists('supervisors', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
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
            $salesmanId = $this->input('salesman_id');
            $branchId = $this->input('branch_id');
            $territoryId = $this->input('territory_id');
            $routeId = $this->input('route_id');
            $supervisorId = $this->input('supervisor_id');
            $effectiveFrom = $this->input('effective_from');
            $effectiveTo = $this->input('effective_to');
            $currentId = $this->route('salesman_assignment')->id ?? null;

            // Validate territory belongs to the selected branch
            if ($territoryId && $branchId) {
                $territory = Territory::where('tenant_id', $tenantId)
                    ->where('id', $territoryId)
                    ->where('branch_id', $branchId)
                    ->first();

                if (! $territory) {
                    $validator->errors()->add('territory_id', 'The selected territory does not belong to the selected branch.');
                }
            }

            // Validate route belongs to the selected territory
            if ($routeId && $territoryId) {
                $route = Route::where('tenant_id', $tenantId)
                    ->where('id', $routeId)
                    ->where('territory_id', $territoryId)
                    ->first();

                if (! $route) {
                    $validator->errors()->add('route_id', 'The selected route does not belong to the selected territory.');
                }
            }

            // Prevent overlapping active assignments for the same salesman
            if ($salesmanId) {
                $overlapping = SalesmanAssignment::where('tenant_id', $tenantId)
                    ->where('salesman_id', $salesmanId)
                    ->where('id', '!=', $currentId)
                    ->where(function ($query) use ($effectiveFrom) {
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $effectiveFrom);
                    })
                    ->where('effective_from', '<=', $effectiveTo ?? '9999-12-31')
                    ->exists();

                if ($overlapping) {
                    $validator->errors()->add('salesman_id', 'This salesman already has an active assignment during this period.');
                }
            }
        });
    }
}
