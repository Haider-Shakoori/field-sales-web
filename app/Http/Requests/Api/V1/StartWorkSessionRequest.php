<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartWorkSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxAccuracy = config('tenancy.tracking.max_accuracy_meters', 200);

        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', "max:{$maxAccuracy}"],
            'offline_uuid' => ['required', 'uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ((float) $this->input('latitude') === 0.0 && (float) $this->input('longitude') === 0.0) {
                $validator->errors()->add('latitude', 'GPS coordinates cannot be 0,0.');
            }
        });
    }
}
