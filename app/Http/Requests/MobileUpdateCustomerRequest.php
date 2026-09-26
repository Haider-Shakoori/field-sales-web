<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileUpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->is_active
            && $user->salesman?->is_active
            && $user->hasPermission('customers:view')
            && $this->route('customer') instanceof Customer;
    }

    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');
        $tenantId = $this->user()->tenant_id;

        return [
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:60',
                Rule::unique('customers', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($customer->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'alternate_phone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius_meters' => ['sometimes', 'integer', 'between:25,1000'],
            'price_list_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
