<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Envelope-level validation only. Individual points are validated and
     * rejected per point by the ingestion service so one bad point cannot
     * reject an entire offline batch.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPoints = config('tenancy.tracking.max_batch_points', 100);

        return [
            'batch_uuid' => ['required', 'uuid'],
            'locations' => ['required', 'array', 'min:1', "max:{$maxPoints}"],
            'locations.*' => ['array'],
        ];
    }
}
