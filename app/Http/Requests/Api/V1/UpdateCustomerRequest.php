<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Route;
use App\Models\Territory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('customers', 'code')->where('tenant_id', $tenantId)->ignore($this->route('customer'))],
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'category_id' => ['nullable', Rule::exists('customer_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'photo_url' => ['nullable', 'string', 'max:500'],
            'assigned_salesman_id' => ['nullable', Rule::exists('salesmen', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'territory_id' => ['required', Rule::exists('territories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'route_id' => ['nullable', Rule::exists('routes', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'outstanding_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'visit_frequency' => ['nullable', 'string', 'max:50', Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenantId = TenantContext::currentId();
            $territoryId = $this->input('territory_id');
            $routeId = $this->input('route_id');
            $branchId = $this->input('branch_id');

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
        });
    }
}
