<?php

namespace App\Http\Requests;

use App\Models\Territory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesRouteRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('routes', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => [
                'string',
                Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
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
                        'The route branch must match the territory branch.'
                    );
                }
            },
        ];
    }
}
