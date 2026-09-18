<?php

namespace App\Http\Requests;

use App\Models\SalesRoute;
use Illuminate\Foundation\Http\FormRequest;

class ReorderRouteCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $route = $this->route('route');

        return $route instanceof SalesRoute
            && ($this->user()?->can('update', $route) ?? false);
    }

    public function rules(): array
    {
        return [
            'positions' => ['required', 'array', 'min:1'],
            'positions.*' => ['required', 'integer', 'between:1,999'],
        ];
    }
}
