<?php

namespace App\Http\Requests;

use App\Models\Supervisor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supervisor = $this->route('supervisor');

        return $supervisor instanceof Supervisor
            && ($this->user()?->can('update', $supervisor) ?? false);
    }

    public function rules(): array
    {
        /** @var Supervisor $supervisor */
        $supervisor = $this->route('supervisor');
        $tenantId = $this->user()->tenant_id;

        return [
            'employee_code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('supervisors', 'employee_code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($supervisor->id),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
