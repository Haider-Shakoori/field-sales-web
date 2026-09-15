@extends('layouts.app')

@section('title', 'Device')

@section('content')
    <x-ui.page-header title="Device"
                      :description="$device->device_uuid">
        <x-slot:actions>
            <x-ui.button href="{{ route('devices.index') }}" variant="ghost" icon="devices">All devices</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Device details" class="xl:col-span-1">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                        <x-ui.icon name="devices" class="h-7 w-7" />
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-50">{{ $device->device_model ?? 'Unknown device' }}</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">{{ $device->manufacturer ?? 'Unknown manufacturer' }}</p>
                    </div>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Status</dt>
                        <dd>
                            <x-ui.status-badge :status="$device->isActive() ? 'active' : 'inactive'" :label="$device->isActive() ? 'Active' : 'Revoked'" />
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Installation UUID</dt>
                        <dd class="truncate pl-4 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $device->installation_uuid }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Android version</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $device->android_version ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">App version</dt>
                        <dd class="font-mono text-gray-700 dark:text-gray-300">{{ $device->app_version ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Registered</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $device->registered_at?->diffForHumans() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Last seen</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Owner</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $device->user?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Salesman</dt>
                        <dd class="text-gray-700 dark:text-gray-300">
                            {{ $device->salesman ? trim($device->salesman->first_name.' '.$device->salesman->last_name) : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </x-ui.card>

        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="Registration info">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Registered {{ $device->registered_at?->toDayDateTimeString() ?? '—' }}.
                    A Sanctum token bound to this device was issued at registration; revoking the device deletes that token
                    and forces the app to re-authenticate.
                </p>
            </x-ui.card>

            @can('revoke', $device)
                <x-ui.card title="Security">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Revoking permanently blocks this device from the API. This cannot be undone.
                        </p>
                        <form method="POST" action="{{ route('devices.revoke', $device) }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" icon="power" :disabled="! $device->isActive()">
                                Revoke device
                            </x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
            @endcan
        </div>
    </div>
@endsection