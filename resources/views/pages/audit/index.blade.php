@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
    <x-ui.page-header title="Audit logs"
                      description="A trail of sensitive actions taken in this company." />

    <form method="GET" action="{{ route('audit.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.input name="event" label="Event" :value="request('event')" placeholder="e.g. user.updated" />
            <x-ui.input name="from" label="From" type="date" :value="request('from')" />
            <x-ui.input name="to" label="To" type="date" :value="request('to')" />
            <div class="flex items-end justify-end gap-2">
                <x-ui.button href="{{ route('audit.index') }}" variant="ghost" type="button">Clear</x-ui.button>
                <x-ui.button type="submit" icon="search">Filter</x-ui.button>
            </div>
        </div>
    </form>

    <x-ui.card>
        @if ($logs->isEmpty())
            <x-ui.empty-state title="No audit entries"
                              icon="clock"
                              description="Sensitive actions will be recorded here as they happen." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">When</th>
                            <th class="px-3 py-3 font-semibold">Actor</th>
                            <th class="px-3 py-3 font-semibold">Event</th>
                            <th class="px-3 py-3 font-semibold">Target</th>
                            <th class="px-3 py-3 font-semibold">Changes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="whitespace-nowrap px-3 py-3 text-gray-400 dark:text-gray-500">
                                    {{ $log->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $log->user?->name ?? 'System' }}
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                                        {{ $log->event }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                    {{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}
                                </td>
                                <td class="max-w-xs px-3 py-3">
                                    <span class="break-words text-xs text-gray-500 dark:text-gray-400">
                                        {{ json_encode($log->new_values ?? [], JSON_UNESCAPED_UNICODE) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$logs" />
        @endif
    </x-ui.card>
@endsection