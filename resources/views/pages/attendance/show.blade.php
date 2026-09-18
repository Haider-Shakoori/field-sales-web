@extends('layouts.app')

@section('title', 'Attendance detail')

@section('content')
    @php
        $timezone = \App\Support\Tracking\TenantClock::timezoneFor(auth()->user());
        $assignment = $session->salesman?->assignments->first(fn ($a) => $a->effective_from->lte($session->date) && ($a->effective_to === null || $a->effective_to->gte($session->date)));
        $statusBadge = match ($session->status) {
            'active' => ['in_progress', 'Active'],
            'completed' => ['completed', 'Completed'],
            'corrected' => ['pending', 'Corrected'],
            'approved' => ['active', 'Approved'],
            default => ['inactive', ucfirst($session->status)],
        };
        $duration = $session->duration_minutes !== null
            ? intdiv($session->duration_minutes, 60).'h '.($session->duration_minutes % 60).'m'
            : '—';
    @endphp

    <x-ui.page-header title="Work session"
                      :description="$session->salesman ? trim($session->salesman->first_name.' '.$session->salesman->last_name).' ('.$session->salesman->employee_code.') · '.($session->date?->format('M j, Y') ?? '') : 'Attendance detail'">
        <x-slot:actions>
            <x-ui.button href="{{ route('attendance.index') }}" variant="ghost" icon="clock">All attendance</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="Work session">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Salesman</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">
                        {{ $session->salesman ? trim($session->salesman->first_name.' '.$session->salesman->last_name) : '—' }}
                    </dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Employee code</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-gray-50">{{ $session->salesman?->employee_code ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment?->branch?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Work date</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->date?->format('M j, Y') ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Started</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->start_time?->timezone($timezone)->format('M j, Y H:i') ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ended</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->end_time?->timezone($timezone)->format('M j, Y H:i') ?? 'Ongoing' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Duration</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $duration }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Device</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">
                        {{ $session->device?->device_model ?? '—' }}@if ($session->device?->app_version) · v{{ $session->device->app_version }}@endif
                    </dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->user?->name ?? '—' }}</dd>
                </dl>
            </x-ui.card>

            <x-ui.card title="GPS check-in">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start latitude</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-gray-50">{{ $session->start_latitude }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start longitude</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-gray-50">{{ $session->start_longitude }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End latitude</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-gray-50">{{ $session->end_latitude ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End longitude</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-gray-50">{{ $session->end_longitude ?? '—' }}</dd>
                </dl>

                @if ($session->is_late_start || $session->is_early_finish)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($session->is_late_start)
                            <x-ui.status-badge status="pending" label="Late start" />
                        @endif
                        @if ($session->is_early_finish)
                            <x-ui.status-badge status="pending" label="Early finish" />
                        @endif
                    </div>
                @endif
            </x-ui.card>

            @if ($session->notes)
                <x-ui.card title="Notes">
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $session->notes }}</p>
                </x-ui.card>
            @endif

            @if ($session->correction_reason || $session->corrected_at || $session->approved_at)
                <x-ui.card title="Correction record">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reason</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->correction_reason ?? '—' }}</dd>

                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Corrected by</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->correctedBy?->name ?? '—' }}</dd>

                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Corrected at</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->corrected_at?->timezone($timezone)->format('M j, Y H:i') ?? '—' }}</dd>

                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved by</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->approvedBy?->name ?? '—' }}</dd>
                    </dl>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">State</dt>
                        <dd>
                            <x-ui.status-badge :status="$statusBadge[0]" :label="$statusBadge[1]" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->created_at?->timezone($timezone)->format('M j, Y H:i') ?? '—' }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $session->updated_at?->timezone($timezone)->format('M j, Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
@endsection
