@extends('layouts.app')

@section('title', 'Salesman Assignment')

@section('content')
    @php
        $isEnded = $assignment->effective_to !== null && $assignment->effective_to->isBefore(today());
        $isScheduled = $assignment->effective_from->isAfter(today());
    @endphp

    <x-ui.page-header title="Salesman assignment"
                      :description="$assignment->salesman ? trim($assignment->salesman->first_name.' '.$assignment->salesman->last_name).' ('.$assignment->salesman->employee_code.')' : 'Assignment details'">
        <x-slot:actions>
            @can('update', $assignment)
                <x-ui.button href="{{ route('salesman-assignments.edit', $assignment) }}" icon="pencil">Edit</x-ui.button>
            @endcan
            <x-ui.button href="{{ route('salesman-assignments.index') }}" variant="ghost" icon="clipboard">All assignments</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="Assignment details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Salesman</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">
                        {{ $assignment->salesman ? trim($assignment->salesman->first_name.' '.$assignment->salesman->last_name) : '—' }}
                    </dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Employee code</dt>
                    <dd class="text-sm font-mono text-gray-900 dark:text-gray-50">{{ $assignment->salesman?->employee_code ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supervisor</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">
                        {{ $assignment->supervisor ? trim($assignment->supervisor->first_name.' '.$assignment->supervisor->last_name) : '—' }}
                    </dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->branch?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Territory</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->territory?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Route</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->route?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Effective from</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->effective_from->format('M j, Y') }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Effective to</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->effective_to?->format('M j, Y') ?? 'Ongoing' }}</dd>
                </dl>
            </x-ui.card>

            @can('deactivate', $assignment)
                <x-ui.card title="Danger zone">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Deleting removes this assignment record. Historical audit entries are preserved.
                        </p>
                        <form method="POST"
                              action="{{ route('salesman-assignments.destroy', $assignment) }}"
                              onsubmit="return confirm('Delete this salesman assignment? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" icon="trash">Delete assignment</x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
            @endcan
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Assignment state</dt>
                        <dd>
                            <x-ui.status-badge :status="$isEnded ? 'inactive' : ($isScheduled ? 'trial' : 'active')"
                                               :label="$isEnded ? 'Ended' : ($isScheduled ? 'Scheduled' : 'Current')" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->created_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->updated_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created by</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $assignment->createdBy?->name ?? 'System' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
@endsection
