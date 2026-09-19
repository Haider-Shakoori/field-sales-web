<?php

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class StartWorkSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `started_at` is optional: online clients keep server-now behaviour, while
     * offline clients may sync the actual event time later.
     *
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
            'started_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ((float) $this->input('latitude') === 0.0 && (float) $this->input('longitude') === 0.0) {
                $validator->errors()->add('latitude', 'GPS coordinates cannot be 0,0.');
            }

            $value = $this->input('started_at');

            if (! is_string($value) || $value === '') {
                return;
            }

            try {
                $event = CarbonImmutable::parse($value)->utc();
            } catch (Throwable) {
                return; // The `date` rule reports malformed values.
            }

            if ($event->greaterThan(CarbonImmutable::now('UTC')->addMinutes(5))) {
                $validator->errors()->add('started_at', 'The started at time cannot be more than 5 minutes in the future.');
            }
        });
    }
}
