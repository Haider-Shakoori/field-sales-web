<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'string', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'android_version' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'push_token' => ['nullable', 'string', 'max:500'],
        ];
    }
}
