@extends('layouts.app')

@section('title', 'Territories')

@section('content')
    <x-ui.page-header title="Territories"
                      description="Geographic regions that group routes and customers.">
        @if (auth()->user()->hasPermission('territories:create'))
            <x-slot:actions>
                <x-ui.button href="{{ route('territories.create') }}" icon="plus">Add territory</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('territories.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Name or code" />
            <x-ui.select name="branch_id" label="Branch" :value="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('territories.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($territories->isEmpty())
            <x-ui.empty-state title="No territories"
                              icon="territories"
                              description="Add territories to organize your routes and customers geographically.">
                @if (auth()->user()->hasPermission('territories:create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('territories.create') }}" variant="primary" icon="plus">Add territory</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Code</th>
                            <th class="px-3 py-3 font-semibold">Name</th>
                            <th class="px-3 py-3 font-semibold">Branch</th>
                            <th class="px-3 py-3 font-semibold">Coordinates</th>
                            <th class="px-3 py-3 font-semibold">Radius (km)</th>
                            <th class="px-3 py-3 font-semibold">Routes</th>
                            <th class="px-3 py-3 font-semibold">Customers</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($territories as $territory)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $territory->code }}</td>
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $territory->name }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $territory->branch?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">
                                    @if ($territory->latitude && $territory->longitude)
                                        {{ $territory->latitude }}, {{ $territory->longitude }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $territory->radius_km ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $territory->routes_count ?? $territory->routes->count() }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $territory->customers_count ?? $territory->customers->count() }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$territory->is_active ? 'active' : 'inactive'" :label="$territory->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('territories.show', $territory) }}"
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

            <x-ui.pagination :paginator="$territories" />
        @endif
    </x-ui.card>
@endsection