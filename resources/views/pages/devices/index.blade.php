@extends('layouts.app')

@section('title', 'Devices')

@section('content')
    <x-ui.page-header title="Devices"
                      description="Mobile devices registered by the field workforce." />

    <form method="GET" action="{{ route('devices.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Device, installation or user" />
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'revoked' => 'Revoked']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('devices.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($devices->isEmpty())
            <x-ui.empty-state title="No devices"
                              icon="devices"
                              description="Devices appear here once the mobile app registers for the first time." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Device</th>
                            <th class="px-3 py-3 font-semibold">User</th>
                            <th class="px-3 py-3 font-semibold">App version</th>
                            <th class="px-3 py-3 font-semibold">Last seen</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($devices as $device)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-ui.icon name="devices" class="h-5 w-5 shrink-0 text-gray-400" />
                                        <div class="min-w-0">
                                            <p class="truncate font-mono text-xs font-medium text-gray-900 dark:text-gray-50">{{ $device->device_uuid }}</p>
                                            <p class="truncate text-xs text-gray-400 dark:text-gray-500">{{ $device->device_model ?? 'Unknown device' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $device->user?->name ?? '—' }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $device->app_version ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-400 dark:text-gray-500">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$device->isActive() ? 'active' : 'inactive'" :label="$device->isActive() ? 'Active' : 'Revoked'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('devices.show', $device) }}"
                                       class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                        <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$devices" />
        @endif
    </x-ui.card>
@endsection