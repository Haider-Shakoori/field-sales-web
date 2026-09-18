<?php

namespace App\Http\Requests;

use App\Models\SalesRoute;
use App\Models\Territory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesmanAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales-team:manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'salesman_id' => ['required', 'integer', Rule::exists('salesmen', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'territory_id' => ['nullable', 'integer', Rule::exists('territories', 'id')->where('tenant_id', $tenantId)],
            'route_id' => ['nullable', 'integer', Rule::exists('routes', 'id')->where('tenant_id', $tenantId)],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('supervisors', 'id')->where('tenant_id', $tenantId)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $branchId = $this->integer('branch_id');
                $territoryId = $this->integer('territory_id');
                $routeId = $this->integer('route_id');
                $territory = $territoryId ? Territory::find($territoryId) : null;
                $route = $routeId ? SalesRoute::find($routeId) : null;

                if ($territory && $branchId && $territory->branch_id && (int) $territory->branch_id !== $branchId) {
                    $validator->errors()->add('territory_id', 'The territory must belong to the selected branch.');
                }

                if ($route && $branchId && $route->branch_id && (int) $route->branch_id !== $branchId) {
                    $validator->errors()->add('route_id', 'The route must belong to the selected branch.');
                }

                if ($route && $territoryId && $route->territory_id && (int) $route->territory_id !== $territoryId) {
                    $validator->errors()->add('route_id', 'The route must belong to the selected territory.');
                }
            },
        ];
    }
}
