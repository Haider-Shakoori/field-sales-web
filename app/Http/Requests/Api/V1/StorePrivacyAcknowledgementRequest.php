<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePrivacyAcknowledgementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Identity fields (tenant_id, user_id, device_id) are never accepted; they
     * are derived from the authenticated device-bound request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'policy_version' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'acknowledged_at' => ['required', 'date'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
