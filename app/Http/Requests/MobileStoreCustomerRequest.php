<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileStoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->is_active
            && $user->salesman?->is_active
            && $user->hasPermission('customers:view');
    }

    public function rules(): array
    {
        return [
            'offline_uuid' => ['required', 'uuid'],
            'code' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'alternate_phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius_meters' => ['nullable', 'integer', 'between:25,1000'],
            'price_list_id' => ['nullable', 'uuid'],
        ];
    }
}
