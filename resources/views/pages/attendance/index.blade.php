@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
    @php
        $timezone = \App\Support\Tracking\TenantClock::timezoneFor(auth()->user());
    @endphp

    <x-ui.page-header title="Attendance"
                      description="Daily work sessions and field check-ins." />

    <form method="GET" action="{{ route('attendance.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.input name="date" type="date" label="Work date" :value="request('date')" />
            <x-ui.select name="salesman_id"
                         label="Salesman"
                         :value="request('salesman_id')"
                         :options="['' => 'All salesmen'] + $salesmen->mapWithKeys(fn ($s) => [$s->id => trim($s->first_name.' '.$s->last_name).' ('.$s->employee_code.')'])->all()" />
            <x-ui.select name="branch_id"
                         label="Branch"
                         :value="request('branch_id')"
                         :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
            <x-ui.select name="status"
                         label="Status"
                         :value="request('status')"
                         :options="['' => 'Any status', 'active' => 'Active', 'completed' => 'Completed', 'corrected' => 'Corrected', 'approved' => 'Approved']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('attendance.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($sessions->isEmpty())
            <x-ui.empty-state title="No attendance records"
                              icon="clock"
                              description="Work sessions will appear here once salesmen start their day from the mobile app." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Salesman</th>
                            <th class="px-3 py-3 font-semibold">Branch</th>
                            <th class="px-3 py-3 font-semibold">Work date</th>
                            <th class="px-3 py-3 font-semibold">Start</th>
                            <th class="px-3 py-3 font-semibold">End</th>
                            <th class="px-3 py-3 font-semibold">Duration</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($sessions as $session)
                            @php
                                $assignment = $session->salesman?->assignments->first(fn ($a) => $a->effective_from->lte($session->date) && ($a->effective_to === null || $a->effective_to->gte($session->date)));
                                $statusBadge = match ($session->status) {
                                    'active' => ['in_progress', 'Active'],
                                    'completed' => ['completed', 'Completed'],
                                    'corrected' => ['pending', 'Corrected'],
                                    'approved' => ['active', 'Approved'],
                                    default => ['inactive', ucfirst($session->status)],
                                };
                                $duration = $session->duration_minutes !== null
                                    ? intdiv($session->duration_minutes, 60).'h '.($session->duration_minutes % 60).'m'
                                    : '—';
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                            {{ strtoupper(substr($session->salesman?->first_name ?? $session->user?->name ?? '?', 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900 dark:text-gray-50">
                                                {{ $session->salesman ? trim($session->salesman->first_name.' '.$session->salesman->last_name) : ($session->user?->name ?? 'Unknown') }}
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $session->salesman?->employee_code ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $assignment?->branch?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $session->date?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $session->start_time?->timezone($timezone)->format('H:i') ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $session->end_time?->timezone($timezone)->format('H:i') ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $duration }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$statusBadge[0]" :label="$statusBadge[1]" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('attendance.show', $session) }}"
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

            <x-ui.pagination :paginator="$sessions" />
        @endif
    </x-ui.card>
@endsection
