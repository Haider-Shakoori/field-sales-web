<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Daily planner') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('Prioritized daily beat plan using sales urgency, follow-ups, visit history and offline distance estimates.') }}</p>
        </div>
    </div>

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-[1fr_180px_auto]">
        <select name="salesman" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            @foreach($salesmen as $salesman)
                <option value="{{ $salesman->uuid }}" @selected($selectedSalesman?->id === $salesman->id)>
                    {{ $salesman->employee_code }} — {{ $salesman->full_name }}
                </option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ $selectedDate }}" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">{{ __('Generate plan') }}</button>
    </form>

    @if(!$selectedSalesman)
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-slate-400">{{ __('No active salesmen found.') }}</div>
    @elseif(($plan['enabled'] ?? true) === false)
        <div class="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-6">
            <h2 class="font-semibold text-amber-200">{{ __('Smart route planning is disabled') }}</h2>
            <p class="mt-2 text-sm text-amber-200/80">{{ __('This organization has disabled route optimization. Existing route, territory and branch assignments are unchanged.') }}</p>
            @if(auth()->user()->hasPermission('settings:view'))
                <a href="{{ route('organization.edit') }}" class="mt-4 inline-flex rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-white/20">{{ __('Open organization settings') }}</a>
            @endif
        </div>
    @elseif(!$plan['source'])
        <div class="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-6">
            <h2 class="font-semibold text-amber-200">{{ __('No assignment source') }}</h2>
            <p class="mt-2 text-sm text-amber-200/80">{{ __('This salesman has no effective route, territory or branch assignment for the selected date.') }}</p>
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">{{ $plan['source']['code'] }} — {{ $plan['source']['name'] }}</h2>
                        <span class="rounded-full bg-indigo-500/15 px-2.5 py-1 text-xs font-semibold text-indigo-300">
                            {{ __(str($plan['source']['type'])->title()->toString()) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ $plan['assignment']['territory'] ?? __('No territory') }}
                        @if($plan['route'])
                            · {{ $plan['route']['scheduled_today'] ? __('Scheduled today') : __('Not normally scheduled today') }}
                        @endif
                    </p>
                </div>
                <div class="text-sm text-slate-400">
                    {{ __('Approx. straight-line distance') }}:
                    <span class="font-semibold text-slate-100">{{ number_format($plan['approximate_air_distance_km'], 1) }} km</span>
                </div>
            </div>

            @if($plan['warnings'])
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($plan['warnings'] as $warning)
                        @php
                            $warningLabel = match(true) {
                                str_starts_with($warning, 'missing_customer_coordinates:') => __('Some customers are missing coordinates').': '.str($warning)->after(':'),
                                $warning === 'route_not_scheduled_today' => __('Route is not normally scheduled today'),
                                default => __('Straight-line distance estimate'),
                            };
                        @endphp
                        <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs text-amber-300">{{ $warningLabel }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Remaining stops') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $plan['summary']['remaining'] }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Urgent / high') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $plan['summary']['urgent'] + $plan['summary']['high'] }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Overdue customers') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $plan['summary']['customers_with_overdue_balance'] }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Planned visit time') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $plan['summary']['planned_visit_minutes'] }} min</p>
            </div>
        </div>

        @if($plan['stops'] === [])
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-center text-slate-400">
                {{ __('No active customers are available for this assignment.') }}
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-white/5">
                        <tr>
                            <th class="px-5 py-3">{{ __('Plan') }}</th>
                            <th class="px-5 py-3">{{ __('Customer') }}</th>
                            <th class="px-5 py-3">{{ __('Priority') }}</th>
                            <th class="px-5 py-3">{{ __('Why') }}</th>
                            <th class="px-5 py-3">{{ __('Financial') }}</th>
                            <th class="px-5 py-3">{{ __('Last visit') }}</th>
                            <th class="px-5 py-3">{{ __('Travel') }}</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                        @foreach($plan['stops'] as $stop)
                            <tr class="{{ $stop['visited_today'] ? 'opacity-60' : '' }}">
                                <td class="px-5 py-4">
                                    <div class="text-lg font-bold">#{{ $stop['recommended_order'] }}</div>
                                    @if($stop['route_sequence'] !== null && $stop['recommended_order'] !== $stop['route_sequence'])
                                        <div class="text-xs text-indigo-300">{{ __('route #') }}{{ $stop['route_sequence'] }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium">{{ $stop['customer_code'] }} — {{ $stop['customer_name'] }}</div>
                                    <div class="mt-1 text-xs text-slate-400">{{ $stop['address'] ?: '—' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $stop['planned_visit_minutes'] }} min</div>
                                </td>
                                <td class="px-5 py-4">
                                    @php
                                        $priorityClass = match($stop['priority']) {
                                            'urgent' => 'bg-rose-500/15 text-rose-300',
                                            'high' => 'bg-amber-500/15 text-amber-300',
                                            'elevated' => 'bg-indigo-500/15 text-indigo-300',
                                            'completed' => 'bg-emerald-500/15 text-emerald-300',
                                            default => 'bg-white/10 text-slate-300',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClass }}">
                                        {{ __(str($stop['priority'])->title()->toString()) }}
                                    </span>
                                </td>
                                <td class="max-w-sm px-5 py-4">
                                    <ul class="space-y-1">
                                        @foreach($stop['reasons'] as $reason)
                                            <li>• {{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-5 py-4">
                                    @forelse($stop['overdue'] as $balance)
                                        <div class="{{ $balance['overdue'] > 0 ? 'font-semibold text-rose-300' : 'text-slate-400' }}">
                                            {{ $balance['currency'] }} {{ number_format($balance['overdue'], 2) }} {{ __('overdue') }}
                                        </div>
                                    @empty
                                        <span class="text-slate-500">—</span>
                                    @endforelse
                                </td>
                                <td class="px-5 py-4">
                                    {{ $stop['last_visited_at'] ? \Carbon\CarbonImmutable::parse($stop['last_visited_at'])->format('Y-m-d') : __('Never') }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ $stop['distance_from_previous_km'] === null ? '—' : number_format($stop['distance_from_previous_km'], 1).' km' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</x-layouts.app>
