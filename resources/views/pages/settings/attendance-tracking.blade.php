@extends('layouts.app')

@section('title', 'Attendance & Tracking')

@section('content')
    <x-ui.page-header title="Attendance & Tracking"
                      description="Company policy for work sessions and GPS tracking. The mobile app follows these settings." />

    @unless ($canManage)
        <x-ui.alert type="info">You have read-only access to these settings.</x-ui.alert>
    @endunless

    @if ($canManage)
        <form method="POST" action="{{ route('settings.attendance-tracking.update') }}" class="max-w-3xl space-y-6">
            @csrf
            @method('PUT')

            <x-ui.card title="Work session">
                <div class="space-y-5">
                    <x-ui.select name="work_session_start_mode"
                                 label="Work session start mode"
                                 :value="old('work_session_start_mode', $settings['work_session_start_mode'])"
                                 :options="['manual' => 'Manual', 'automatic' => 'Automatic']"
                                 required />
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Manual: the salesman must tap Start Day. Automatic: the mobile app begins the work session when the
                        app is available during the configured work period and the required privacy and location
                        permissions are satisfied. Android cannot force-start a force-stopped application.
                    </p>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-ui.input name="workday_start_time"
                                    type="time"
                                    label="Workday start time"
                                    :value="old('workday_start_time', $settings['workday_start_time'])" />
                        <x-ui.input name="workday_end_time"
                                    type="time"
                                    label="Workday end time"
                                    :value="old('workday_end_time', $settings['workday_end_time'])"
                                    hint="A time earlier than the start time is treated as an overnight shift." />
                    </div>

                    <x-ui.toggle name="auto_end_session"
                                 label="Auto end work session"
                                 :checked="(bool) old('auto_end_session', $settings['auto_end_session'])"
                                 hint="The mobile app ends the session at the workday end time when automatic mode and permissions allow." />
                </div>
            </x-ui.card>

            <x-ui.card title="GPS tracking">
                <div class="space-y-5">
                    <x-ui.toggle name="gps_tracking_enabled"
                                 label="GPS tracking enabled"
                                 :checked="(bool) old('gps_tracking_enabled', $settings['gps_tracking_enabled'])"
                                 hint="Disabling pauses continuous background tracking. Start/End Day still records the check-in location." />

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <x-ui.input name="gps_moving_interval_seconds"
                                    type="number"
                                    label="Moving interval (seconds)"
                                    :value="old('gps_moving_interval_seconds', $settings['gps_moving_interval_seconds'])" />
                        <x-ui.input name="gps_stationary_interval_seconds"
                                    type="number"
                                    label="Stationary interval (seconds)"
                                    :value="old('gps_stationary_interval_seconds', $settings['gps_stationary_interval_seconds'])" />
                        <x-ui.input name="gps_stale_after_minutes"
                                    type="number"
                                    label="Offline / stale after (minutes)"
                                    :value="old('gps_stale_after_minutes', $settings['gps_stale_after_minutes'])" />
                    </div>
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-3">
                        <x-ui.button href="{{ route('settings.company.edit') }}" variant="ghost" type="button">Cancel</x-ui.button>
                        <x-ui.button type="submit">Save settings</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.card>
        </form>
    @else
        <x-ui.card title="Effective policy">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Work session start mode</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-50">{{ ucfirst($settings['work_session_start_mode']) }}</dd>

                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Workday hours</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-50">
                    {{ $settings['workday_start_time'] ?? '—' }} – {{ $settings['workday_end_time'] ?? '—' }}
                </dd>

                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Auto end work session</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $settings['auto_end_session'] ? 'Enabled' : 'Disabled' }}</dd>

                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">GPS tracking</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $settings['gps_tracking_enabled'] ? 'Enabled' : 'Disabled' }}</dd>

                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Moving / stationary interval</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-50">
                    {{ $settings['gps_moving_interval_seconds'] }}s / {{ $settings['gps_stationary_interval_seconds'] }}s
                </dd>

                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Offline / stale after</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $settings['gps_stale_after_minutes'] }} minutes</dd>
            </dl>
        </x-ui.card>
    @endif

    <x-ui.card title="Information" class="max-w-3xl">
        <dl class="space-y-3">
            <div class="flex flex-wrap justify-between gap-2">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Company timezone</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $timezone }}</dd>
            </div>
            @if ($settingsUpdatedAt)
                <div class="flex flex-wrap justify-between gap-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last updated</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $settingsUpdatedAt->timezone($timezone)->format('M j, Y H:i') }}</dd>
                </div>
            @endif
        </dl>
        <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
            Work dates and workday windows follow the company timezone; session timestamps are stored in UTC.
            The timezone is managed in Company Settings.
        </p>
    </x-ui.card>
@endsection
