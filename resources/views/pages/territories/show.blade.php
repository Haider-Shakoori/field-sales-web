@extends('layouts.app')

@section('title', 'Territory: {{ $territory->name }}')

@section('content')
    <x-ui.page-header title="{{ $territory->name }}"
                      description="Code: {{ $territory->code }} — Branch: {{ $territory->branch?->name }}">
        @if (auth()->user()->hasPermission('territories:update'))
            <x-slot:actions>
                <x-ui.button href="{{ route('territories.edit', $territory) }}" icon="pencil">Edit</x-ui.button>
            </x-slot:actions>
        @endif

        @if (auth()->user()->hasPermission('territories:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('territories.deactivate', $territory) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $territory->is_active ? 'danger' : 'success' }}" icon="{{ $territory->is_active ? 'map-pin-off' : 'map-pin' }}">
                        {{ $territory->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Territory Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">{{ $territory->code }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $territory->name }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $territory->branch?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 sm:col-span-2">{{ $territory->description ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Center Coordinates</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">
                        @if ($territory->latitude && $territory->longitude)
                            {{ $territory->latitude }}, {{ $territory->longitude }}
                        @else
                            Not set
                        @endif
                    </dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Radius</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $territory->radius_km ?? 'Not set' }} km</dd>
                </dl>
            </x-ui.card>

            <x-ui.card title="Routes ({{ $territory->routes->count() }})">
                @if ($territory->routes->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400 py-4">No routes in this territory.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                    <th class="px-3 py-3 font-semibold">Code</th>
                                    <th class="px-3 py-3 font-semibold">Name</th>
                                    <th class="px-3 py-3 font-semibold">Weekday</th>
                                    <th class="px-3 py-3 font-semibold">Customers</th>
                                    <th class="px-3 py-3 font-semibold">Status</th>
                                    <th class="px-3 py-3 text-right font-semibold"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($territory->routes as $route)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $route->code }}</td>
                                        <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $route->name }}</td>
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
                @endif
            </x-ui-card>

            @if ($territory->salesmanAssignments()->exists() || $territory->supervisorAssignments()->exists())
                <x-ui.card title="Assignments">
                    @if ($territory->salesmanAssignments()->exists())
                        <h4 class="font-medium text-gray-900 dark:text-gray-50 mb-2">Salesmen</h4>
                        <ul class="space-y-1">
                            @foreach ($territory->salesmanAssignments as $assignment)
                                <li class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $assignment->salesman->first_name }} {{ $assignment->salesman->last_name }} ({{ $assignment->salesman->employee_code }})
                                    @if ($assignment->isCurrentlyActive()) <span class="ml-2 text-green-600 dark:text-green-400">(Active)</span> @endif
                                    — {{ $assignment->effective_from->format('Y-m-d') }} to {{ $assignment->effective_to?->format('Y-m-d') ?? 'Present' }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($territory->supervisorAssignments()->exists())
                        <h4 class="font-medium text-gray-900 dark:text-gray-50 mt-4 mb-2">Supervisors</h4>
                        <ul class="space-y-1">
                            @foreach ($territory->supervisorAssignments as $assignment)
                                <li class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $assignment->supervisor->first_name }} {{ $assignment->supervisor->last_name }} ({{ $assignment->supervisor->employee_code }})
                                    @if ($assignment->isCurrentlyActive()) <span class="ml-2 text-green-600 dark:text-green-400">(Active)</span> @endif
                                    — {{ $assignment->effective_from->format('Y-m-d') }} to {{ $assignment->effective_to?->format('Y-m-d') ?? 'Present' }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">
                            <x-ui.status-badge :status="$territory->is_active ? 'active' : 'inactive'" :label="$territory->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $territory->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $territory->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    @if ($territory->deleted_at)
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Deactivated</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $territory->deleted_at->format('Y-m-d H:i:s') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>
        </div>
    </div>
@endsection