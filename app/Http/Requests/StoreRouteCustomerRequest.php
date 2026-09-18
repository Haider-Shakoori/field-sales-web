<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\SalesRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRouteCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $route = $this->route('route');

        return $route instanceof SalesRoute
            && ($this->user()?->can('update', $route) ?? false);
    }

    public function rules(): array
    {
        /** @var SalesRoute $route */
        $route = $this->route('route');
        $tenantId = $this->user()->tenant_id;

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
                Rule::unique('route_customers', 'customer_id')
                    ->where('route_id', $route->id),
            ],
            'planned_visit_minutes' => ['required', 'integer', 'between:1,480'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var SalesRoute $route */
                $route = $this->route('route');
                $customerId = $this->integer('customer_id');

                if (! $customerId) {
                    return;
                }

                $customer = Customer::find($customerId);

                if (! $customer) {
                    return;
                }

                if ($route->branch_id && $customer->branch_id
                    && (int) $route->branch_id !== (int) $customer->branch_id) {
                    $validator->errors()->add(
                        'customer_id',
                        'The customer branch does not match this route.'
                    );
                }

                if ($route->territory_id && $customer->territory_id
                    && (int) $route->territory_id !== (int) $customer->territory_id) {
                    $validator->errors()->add(
                        'customer_id',
                        'The customer territory does not match this route.'
                    );
                }
            },
        ];
    }
}
