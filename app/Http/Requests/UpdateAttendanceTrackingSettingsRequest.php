<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceTrackingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings:manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_session_start_mode' => ['required', Rule::in(['manual', 'automatic'])],
            'workday_start_time' => ['nullable', 'date_format:H:i', 'required_if:work_session_start_mode,automatic'],
            'workday_end_time' => ['nullable', 'date_format:H:i', 'required_if:work_session_start_mode,automatic'],
            'auto_end_session' => ['nullable', 'boolean'],
            'gps_tracking_enabled' => ['nullable', 'boolean'],
            'gps_moving_interval_seconds' => ['required', 'integer', 'between:10,3600'],
            'gps_stationary_interval_seconds' => ['required', 'integer', 'between:10,3600', 'gte:gps_moving_interval_seconds'],
            'gps_stale_after_minutes' => ['required', 'integer', 'between:1,240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gps_stationary_interval_seconds.gte' => 'The stationary interval must be greater than or equal to the moving interval.',
            'workday_start_time.required_if' => 'A workday start time is required when automatic mode is enabled.',
            'workday_end_time.required_if' => 'A workday end time is required when automatic mode is enabled.',
        ];
    }
}
