<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            && ($this->user()?->can('update', $branch) ?? false);
    }

    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('branches', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($branch->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
