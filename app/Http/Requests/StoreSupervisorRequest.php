<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales-team:manage') ?? false;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $userId = $this->integer('user_id');

                if ($userId && DB::table('salesmen')->where('user_id', $userId)->exists()) {
                    $validator->errors()->add(
                        'user_id',
                        'This user already has the other sales-team profile type.'
                    );
                }
            },
        ];
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
                Rule::unique('supervisors', 'user_id'),
            ],
            'employee_code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('supervisors', 'employee_code')->where('tenant_id', $tenantId),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
