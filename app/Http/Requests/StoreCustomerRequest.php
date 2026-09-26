<?php

namespace App\Http\Requests;

use App\Models\Territory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers:manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'territory_id' => [
                'nullable',
                'integer',
                Rule::exists('territories', 'id')->where('tenant_id', $tenantId),
            ],
            'price_list_id' => [
                'nullable',
                'integer',
                Rule::exists('price_lists', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('customers', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'alternate_phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.9999'],
            'credit_currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'credit_terms_days' => ['nullable', 'integer', 'between:0,365'],
            'geofence_radius_meters' => ['required', 'integer', 'between:25,1000'],
            'offline_uuid' => [
                'nullable',
                'uuid',
                Rule::unique('customers', 'offline_uuid')->where('tenant_id', $tenantId),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $territoryId = $this->integer('territory_id');
                $branchId = $this->integer('branch_id');

                if (! $territoryId || ! $branchId) {
                    return;
                }

                $territory = Territory::find($territoryId);

                if ($territory?->branch_id && (int) $territory->branch_id !== $branchId) {
                    $validator->errors()->add(
                        'branch_id',
                        'The selected customer branch must match the territory branch.'
                    );
                }
            },
        ];
    }
}
