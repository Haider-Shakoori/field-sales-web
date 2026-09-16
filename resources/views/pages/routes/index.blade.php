@extends('layouts.app')

@section('title', 'Routes')

@section('content')
    <x-ui.page-header title="Routes"
                      description="Ordered sequences of customers to visit within a territory.">
        @if (auth()->user()->hasPermission('routes:create'))
            <x-slot:actions>
                <x-ui.button href="{{ route('routes.create') }}" icon="plus">Add route</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('routes.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Name or code" />
            <x-ui.select name="territory_id" label="Territory" :value="request('territory_id')" :options="['' => 'All territories'] + $territories->pluck('name', 'id')->all()" />
            <x-ui.select name="weekday" label="Weekday" :value="request('weekday')" :options="['' => 'Any day', '0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday']" />
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('routes.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($routes->isEmpty())
            <x-ui.empty-state title="No routes"
                              icon="routes"
                              description="Add routes to plan customer visit sequences within territories.">
                @if (auth()->user()->hasPermission('routes:create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('routes.create') }}" variant="primary" icon="plus">Add route</x-ui.button>
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
                            <th class="px-3 py-3 font-semibold">Territory</th>
                            <th class="px-3 py-3 font-semibold">Weekday</th>
                            <th class="px-3 py-3 font-semibold">Customers</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($routes as $route)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $route->code }}</td>
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $route->name }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $route->territory?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                    @if ($route->weekday !== null)
                                        {{ ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$route->weekday] }}
                                    @else
                                        Any day
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $route->routeCustomers->count() }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$route->is_active ? 'active' : 'inactive'" :label="$route->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('routes.show', $route) }}"
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

            <x-ui.pagination :paginator="$routes" />
        @endif
    </x-ui.card>
@endsection