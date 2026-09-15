@extends('layouts.app')

@section('title', 'Salesmen')

@section('content')
    <x-ui.page-header title="Salesmen"
                      description="Field sales representatives and their devices.">
        @if (auth()->user()->hasPermission('salesmen:create'))
            <x-slot:actions>
                <x-ui.button href="{{ route('salesmen.create') }}" icon="plus">Add salesman</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('salesmen.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Name, code or phone" />
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('salesmen.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($salesmen->isEmpty())
            <x-ui.empty-state title="No salesmen"
                              icon="salesmen"
                              description="Add field representatives to manage their profiles and devices.">
                @if (auth()->user()->hasPermission('salesmen:create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('salesmen.create') }}" variant="primary" icon="plus">Add salesman</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Name</th>
                            <th class="px-3 py-3 font-semibold">Employee code</th>
                            <th class="px-3 py-3 font-semibold">Phone</th>
                            <th class="px-3 py-3 font-semibold">Devices</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($salesmen as $salesman)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                            {{ strtoupper(substr($salesman->first_name, 0, 1)) }}
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-gray-50">
                                            {{ trim($salesman->first_name.' '.$salesman->last_name) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $salesman->employee_code }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $salesman->phone ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $salesman->devices->count() }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$salesman->is_active ? 'active' : 'inactive'" :label="$salesman->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('salesmen.show', $salesman) }}"
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

            <x-ui.pagination :paginator="$salesmen" />
        @endif
    </x-ui.card>
@endsection