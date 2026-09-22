<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Daily beat plans') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('Offline-aware customer sequencing using sales urgency, follow-ups and coordinates.') }}</p>
        </div>
    </div>

    <div class="mb-5 grid gap-4 rounded-2xl border border-white/10 bg-slate-900 p-5 lg:grid-cols-[1fr_auto]">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[180px_280px_auto]">
            <input type="date" name="date" value="{{ $date }}" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <select name="salesman" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                <option value="">{{ __('All salesmen') }}</option>
                @foreach($salesmen as $salesman)
                    <option value="{{ $salesman->uuid }}" @selected($selectedSalesman?->id === $salesman->id)>
                        {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                    </option>
                @endforeach
            </select>
            <button class="rounded-xl bg-white/10 px-4 py-3 font-semibold">{{ __('Filter') }}</button>
        </form>

        @if(auth()->user()->hasPermission('sales-team:manage'))
            <form method="POST" action="{{ route('admin.daily-plans.generate') }}" class="flex flex-wrap gap-2">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <select name="salesman" class="min-w-56 rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                    <option value="">{{ __('Select salesman') }}</option>
                    @foreach($salesmen as $salesman)
                        <option value="{{ $salesman->uuid }}" @selected($selectedSalesman?->id === $salesman->id)>
                            {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                        </option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">
                    {{ $selectedPlan ? __('Regenerate plan') : __('Generate plan') }}
                </button>
            </form>
        @endif
    </div>

    @if($selectedPlan)
        @php
            $completed = $selectedPlan->stops->whereNotNull('completed_visit_id')->count();
            $distanceKm = $selectedPlan->estimated_distance_m / 1000;
        @endphp

        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Salesman') }}</p>
                <p class="mt-2 font-semibold">{{ $selectedPlan->salesman?->full_name }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Stops') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $selectedPlan->total_stops }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Completed') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $completed }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Visit time') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $selectedPlan->planned_visit_minutes }}m</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Estimated distance') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($distanceKm, 1) }} km</p>
            </div>
        </div>

        <div class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ __('Optimized stop sequence') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ __('Source') }}:
                        {{ $selectedPlan->route?->name ?? __(str($selectedPlan->source_type)->title()->toString()) }}
                        · {{ __('Algorithm') }}: {{ $selectedPlan->algorithm_version }}
                    </p>
                </div>
                @if($selectedPlan->warnings)
                    <div class="flex flex-wrap gap-2">
                        @foreach($selectedPlan->warnings as $warning)
                            <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs text-amber-300">
                                {{ str($warning)->replace('_', ' ')->replace(':', ': ')->title() }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-white/5">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Priority') }}</th>
                        <th class="px-4 py-3">{{ __('Why') }}</th>
                        <th class="px-4 py-3">{{ __('From previous') }}</th>
                        <th class="px-4 py-3">{{ __('Visit time') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                    @foreach($selectedPlan->stops as $stop)
                        <tr>
                            <td class="px-4 py-4 font-semibold">{{ $stop->sequence_number }}</td>
                            <td class="px-4 py-4">
                                <a href="{{ route('admin.customers.show', $stop->customer) }}" class="font-medium hover:text-indigo-300">
                                    {{ $stop->customer?->code }} — {{ $stop->customer?->name }}
                                </a>
                                <div class="mt-1 text-xs text-slate-400">{{ $stop->customer?->address }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $stop->priority_tier === 'urgent' ? 'bg-rose-500/15 text-rose-300' : '' }}
                                    {{ $stop->priority_tier === 'high' ? 'bg-amber-500/15 text-amber-300' : '' }}
                                    {{ $stop->priority_tier === 'normal' ? 'bg-indigo-500/15 text-indigo-300' : '' }}
                                    {{ $stop->priority_tier === 'routine' ? 'bg-white/10 text-slate-300' : '' }}">
                                    {{ __(str($stop->priority_tier)->title()->toString()) }} · {{ $stop->priority_score }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex max-w-sm flex-wrap gap-1">
                                    @forelse($stop->reason_codes ?? [] as $reason)
                                        <span class="rounded-full bg-white/5 px-2 py-1 text-xs text-slate-300">
                                            {{ str($reason)->replace('_', ' ')->title() }}
                                        </span>
                                    @empty
                                        <span class="text-slate-500">{{ __('Routine route stop') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @if($stop->estimated_distance_from_previous_m === null)
                                    —
                                @elseif($stop->estimated_distance_from_previous_m >= 1000)
                                    {{ number_format($stop->estimated_distance_from_previous_m / 1000, 1) }} km
                                @else
                                    {{ $stop->estimated_distance_from_previous_m }} m
                                @endif
                            </td>
                            <td class="px-4 py-4">{{ $stop->planned_visit_minutes }}m</td>
                            <td class="px-4 py-4">
                                @if($stop->completed_visit_id)
                                    <span class="text-emerald-300">{{ __('Completed') }}</span>
                                @else
                                    <span class="text-slate-400">{{ __('Pending') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4">
                <h2 class="font-semibold">{{ __('Plans for selected date') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">{{ __('Salesman') }}</th>
                        <th class="px-5 py-3">{{ __('Source') }}</th>
                        <th class="px-5 py-3">{{ __('Stops') }}</th>
                        <th class="px-5 py-3">{{ __('Completed') }}</th>
                        <th class="px-5 py-3">{{ __('Distance') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                    @forelse($plans as $plan)
                        <tr>
                            <td class="px-5 py-4">{{ $plan->salesman?->employee_code }} · {{ $plan->salesman?->full_name }}</td>
                            <td class="px-5 py-4">{{ $plan->route?->name ?? __(str($plan->source_type)->title()->toString()) }}</td>
                            <td class="px-5 py-4">{{ $plan->stops_count }}</td>
                            <td class="px-5 py-4">{{ $plan->completed_stops_count }}</td>
                            <td class="px-5 py-4">{{ number_format($plan->estimated_distance_m / 1000, 1) }} km</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('admin.daily-plans.index', ['date' => $date, 'salesman' => $plan->salesman?->uuid]) }}" class="rounded-lg bg-white/10 px-3 py-2">
                                    {{ __('View') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-slate-400">
                                {{ __('No daily plans generated for this date.') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
