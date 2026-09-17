@extends('layouts.app')

@section('title', 'Supervisor Assignments')

@section('content')
    <x-ui.page-header title="Supervisor Assignments"
                      description="Manage current and historical supervisor assignments.">
        @if (auth()->user()->hasPermission('assignments:manage'))
            <x-slot:actions>
                <x-ui.button href="{{ route('supervisor-assignments.create') }}" icon="plus">New assignment</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('supervisor-assignments.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Supervisor name or employee code" />
            <x-ui.select name="supervisor_id"
                         label="Supervisor"
                         :value="request('supervisor_id')"
                         :options="['' => 'All supervisors'] + $supervisors->mapWithKeys(fn ($s) => [$s->id => trim($s->first_name.' '.$s->last_name).' ('.$s->employee_code.')'])->all()" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('supervisor-assignments.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($assignments->isEmpty())
            <x-ui.empty-state title="No supervisor assignments"
                              icon="clipboard"
                              description="Assignments will appear here once supervisors are allocated to branches and territories.">
                @if (auth()->user()->hasPermission('assignments:manage'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('supervisor-assignments.create') }}" variant="primary" icon="plus">New assignment</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Supervisor</th>
                            <th class="px-3 py-3 font-semibold">Branch</th>
                            <th class="px-3 py-3 font-semibold">Territory</th>
                            <th class="px-3 py-3 font-semibold">Effective period</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($assignments as $assignment)
                            @php
                                $isEnded = $assignment->effective_to !== null && $assignment->effective_to->isBefore(today());
                                $isScheduled = $assignment->effective_from->isAfter(today());
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                            {{ strtoupper(substr($assignment->supervisor?->first_name ?? '?', 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900 dark:text-gray-50">
                                                {{ $assignment->supervisor ? trim($assignment->supervisor->first_name.' '.$assignment->supervisor->last_name) : 'Unknown supervisor' }}
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $assignment->supervisor?->employee_code ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $assignment->branch?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $assignment->territory?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                    {{ $assignment->effective_from->format('M j, Y') }} – {{ $assignment->effective_to?->format('M j, Y') ?? 'Ongoing' }}
                                </td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$isEnded ? 'inactive' : ($isScheduled ? 'trial' : 'active')"
                                                       :label="$isEnded ? 'Ended' : ($isScheduled ? 'Scheduled' : 'Current')" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('supervisor-assignments.show', $assignment) }}"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                            <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                            View
                                        </a>
                                        @can('update', $assignment)
                                            <a href="{{ route('supervisor-assignments.edit', $assignment) }}"
                                               class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700/60">
                                                <x-ui.icon name="pencil" class="h-3.5 w-3.5" />
                                                Edit
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$assignments" />
        @endif
    </x-ui.card>
@endsection
