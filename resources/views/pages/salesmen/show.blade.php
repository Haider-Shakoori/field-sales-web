@extends('layouts.app')

@section('title', 'Salesman')

@section('content')
    <x-ui.page-header :title="trim($salesman->first_name.' '.$salesman->last_name)"
                      :description="'Employee '.$salesman->employee_code">
        <x-slot:actions>
            <x-ui.button href="{{ route('salesmen.index') }}" variant="ghost" icon="salesmen">All salesmen</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Profile" class="xl:col-span-1">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-lg font-bold text-white">
                        {{ strtoupper(substr($salesman->first_name, 0, 1)) }}
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-50">{{ $salesman->employee_code }}</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Hired {{ $salesman->hire_date?->format('M Y') ?? '—' }}</p>
                    </div>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Status</dt>
                        <dd>
                            <x-ui.status-badge :status="$salesman->is_active ? 'active' : 'inactive'" :label="$salesman->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Designation</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $salesman->designation ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Phone</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $salesman->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Email</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $salesman->email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Linked user</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $salesman->user?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </x-ui.card>

        <div class="space-y-6 xl:col-span-2">
            @can('update', $salesman)
                <form method="POST" action="{{ route('salesmen.update', $salesman) }}">
                    @csrf
                    @method('PUT')

                    <x-ui.card title="Edit salesman">
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-ui.input name="employee_code" label="Employee code" :value="old('employee_code', $salesman->employee_code)" />
                                <x-ui.input name="first_name" label="First name" :value="old('first_name', $salesman->first_name)" required />
                            </div>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-ui.input name="last_name" label="Last name" :value="old('last_name', $salesman->last_name)" />
                                <x-ui.input name="phone" label="Phone" :value="old('phone', $salesman->phone)" />
                            </div>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-ui.input name="email" type="email" label="Email" :value="old('email', $salesman->email)" />
                                <x-ui.input name="hire_date" type="date" label="Hire date" :value="old('hire_date', $salesman->hire_date?->toDateString())" />
                            </div>
                            <x-ui.input name="designation" label="Designation" :value="old('designation', $salesman->designation)" />
                        </div>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button href="{{ route('salesmen.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                                <x-ui.button type="submit">Save changes</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.card>
                </form>
            @endcan

            <x-ui.card title="Devices">
                @if ($salesman->devices->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No devices registered for this salesman yet. Devices appear here once the mobile app signs in.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                    <th class="px-3 py-3 font-semibold">Device</th>
                                    <th class="px-3 py-3 font-semibold">Last seen</th>
                                    <th class="px-3 py-3 font-semibold">Status</th>
                                    <th class="px-3 py-3 text-right font-semibold"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($salesman->devices as $device)
                                    <tr>
                                        <td class="px-3 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $device->device_uuid }}</td>
                                        <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                        <td class="px-3 py-3">
                                            <x-ui.status-badge :status="$device->isActive() ? 'active' : 'inactive'" :label="$device->isActive() ? 'Active' : 'Revoked'" />
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <a href="{{ route('devices.show', $device) }}" class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>

            @can('deactivate', $salesman)
                <x-ui.card title="Danger zone">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $salesman->is_active ? 'Deactivating marks this salesman inactive and blocks field sign-in.' : 'This salesman is currently inactive.' }}
                        </p>
                        <form method="POST" action="{{ route('salesmen.deactivate', $salesman) }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" icon="power">
                                {{ $salesman->is_active ? 'Deactivate' : 'Activate' }}
                            </x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
            @endcan
        </div>
    </div>
@endsection