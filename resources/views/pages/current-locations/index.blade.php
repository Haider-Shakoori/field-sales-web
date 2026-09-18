@extends('layouts.app')

@section('title', 'Current Locations')

@section('content')
    @php
        $timezone = \App\Support\Tracking\TenantClock::timezoneFor(auth()->user());
        $staleAfter = (int) $staleAfterMinutes;
        $stalenessCutoff = now('UTC')->subMinutes($staleAfter);
    @endphp

    <x-ui.page-header title="Current Locations"
                      description="Latest positions reported by the mobile tracking service." />

    <form method="GET" action="{{ route('current-locations.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.select name="salesman_id"
                         label="Salesman"
                         :value="request('salesman_id')"
                         :options="['' => 'All salesmen'] + $salesmen->mapWithKeys(fn ($s) => [$s->id => trim($s->first_name.' '.$s->last_name).' ('.$s->employee_code.')'])->all()" />
            <x-ui.select name="mock_only"
                         label="GPS signal"
                         :value="request('mock_only')"
                         :options="['' => 'All locations', '1' => 'Mock locations only']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('current-locations.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($locations->isEmpty())
            <x-ui.empty-state title="No current locations"
                              icon="map"
                              description="Latest salesman positions will appear here once tracking data is synced from the mobile app." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Salesman</th>
                            <th class="px-3 py-3 font-semibold">Branch</th>
                            <th class="px-3 py-3 font-semibold">Position</th>
                            <th class="px-3 py-3 font-semibold">Accuracy</th>
                            <th class="px-3 py-3 font-semibold">Speed</th>
                            <th class="px-3 py-3 font-semibold">Battery</th>
                            <th class="px-3 py-3 font-semibold">Network</th>
                            <th class="px-3 py-3 font-semibold">Recorded</th>
                            <th class="px-3 py-3 font-semibold">Signal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($locations as $location)
                            @php
                                $assignment = $location->salesman?->assignments->first(fn ($a) => $a->effective_to === null || $a->effective_to->gte(today()));
                                $isStale = $location->recorded_at !== null && $location->recorded_at->lt($stalenessCutoff);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                            {{ strtoupper(substr($location->salesman?->first_name ?? '?', 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900 dark:text-gray-50">
                                                {{ $location->salesman ? trim($location->salesman->first_name.' '.$location->salesman->last_name) : 'Unknown' }}
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $location->salesman?->employee_code ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $assignment?->branch?->name ?? '—' }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format((float) $location->latitude, 5) }}, {{ number_format((float) $location->longitude, 5) }}
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $location->horizontal_accuracy !== null ? number_format((float) $location->horizontal_accuracy, 1).' m' : '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $location->speed !== null ? number_format((float) $location->speed, 1).' m/s' : '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                    {{ $location->battery_level !== null ? $location->battery_level.'%' : '—' }}@if ($location->is_charging) <span class="text-xs text-green-600 dark:text-green-400">charging</span>@endif
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $location->network_status ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                    <div>{{ $location->recorded_at?->timezone($timezone)->format('M j, H:i:s') ?? '—' }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">received {{ $location->received_at?->timezone($timezone)->diffForHumans() ?? '—' }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap items-center gap-1">
                                        @if ($location->is_mock_location)
                                            <x-ui.status-badge status="error" label="Mock" />
                                        @endif
                                        <x-ui.status-badge :status="$isStale ? 'inactive' : 'active'" :label="$isStale ? 'Stale' : 'Fresh'" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$locations" />
        @endif
    </x-ui.card>
@endsection
