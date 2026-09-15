@extends('layouts.app')

@section('title', 'Supervisor')

@section('content')
    <x-ui.page-header :title="trim($supervisor->first_name.' '.$supervisor->last_name)"
                      :description="'Employee '.$supervisor->employee_code">
        <x-slot:actions>
            <x-ui.button href="{{ route('supervisors.index') }}" variant="ghost" icon="users">All supervisors</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Profile" class="xl:col-span-1">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-lg font-bold text-white">
                        {{ strtoupper(substr($supervisor->first_name, 0, 1)) }}
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-50">{{ $supervisor->employee_code }}</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Added {{ $supervisor->created_at?->format('M Y') }}</p>
                    </div>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Status</dt>
                        <dd>
                            <x-ui.status-badge :status="$supervisor->is_active ? 'active' : 'inactive'" :label="$supervisor->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Phone</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $supervisor->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Linked user</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $supervisor->user?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </x-ui.card>

        <div class="space-y-6 xl:col-span-2">
            @can('update', $supervisor)
                <form method="POST" action="{{ route('supervisors.update', $supervisor) }}">
                    @csrf
                    @method('PUT')

                    <x-ui.card title="Edit supervisor">
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-ui.input name="employee_code" label="Employee code" :value="old('employee_code', $supervisor->employee_code)" />
                                <x-ui.input name="first_name" label="First name" :value="old('first_name', $supervisor->first_name)" required />
                            </div>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-ui.input name="last_name" label="Last name" :value="old('last_name', $supervisor->last_name)" />
                                <x-ui.input name="phone" label="Phone" :value="old('phone', $supervisor->phone)" />
                            </div>
                        </div>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button href="{{ route('supervisors.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                                <x-ui.button type="submit">Save changes</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.card>
                </form>
            @endcan

            @can('deactivate', $supervisor)
                <x-ui.card title="Danger zone">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $supervisor->is_active ? 'Deactivating marks this supervisor inactive.' : 'This supervisor is currently inactive.' }}
                        </p>
                        <form method="POST" action="{{ route('supervisors.deactivate', $supervisor) }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" icon="power">
                                {{ $supervisor->is_active ? 'Deactivate' : 'Activate' }}
                            </x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
            @endcan
        </div>
    </div>
@endsection