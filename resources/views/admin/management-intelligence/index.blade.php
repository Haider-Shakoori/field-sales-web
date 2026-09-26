<x-layouts.app>
    @php
        $summary = $intelligence['summary'];
        $reasonLabels = [
            'attendance_not_started' => __('Workday started but attendance has not started'),
            'late_start' => __('Late attendance start'),
            'missed_planned_visits' => __('Missed planned visits'),
            'route_behind_expected_progress' => __('Route progress is behind the workday pace'),
            'route_capacity_risk' => __('Route exceeds planned workday capacity'),
            'off_route_visits' => __('Off-route visits recorded'),
            'urgent_route_stops_remaining' => __('Urgent route stops remain'),
            'pending_order_approvals' => __('Orders waiting for approval'),
            'pending_collection_verification' => __('Collections waiting for verification'),
            'overdue_follow_ups' => __('Overdue customer follow-ups'),
            'unresolved_visit_flags' => __('Unresolved visit flags'),
            'target_below_attention_threshold' => __('Target progress below attention threshold'),
        ];
        $levelClasses = [
            'high' => 'border-rose-400/20 bg-rose-500/10 text-rose-200',
            'watch' => 'border-amber-400/20 bg-amber-500/10 text-amber-200',
            'clear' => 'border-emerald-400/20 bg-emerald-500/10 text-emerald-200',
        ];
        $money = static function (array $totals): string {
            if ($totals === []) {
                return '—';
            }

            return collect($totals)
                ->map(fn (array $row) => number_format((float) $row['total'], 2).' '.$row['currency'])
                ->implode(' · ');
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ __('Management intelligence') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-400">{{ __('A live management view of attendance, route execution, approvals, collections, follow-ups, targets and field exceptions using verified FieldPulse data.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.daily-planner.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Daily planner') }}</a>
            <a href="{{ route('admin.scorecards.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Scorecards') }}</a>
            <a href="{{ route('admin.alerts.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Alerts') }}</a>
        </div>
    </div>

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-[180px_minmax(220px,1fr)_140px]">
        <label>
            <span class="mb-1 block text-xs text-slate-500">{{ __('Date') }}</span>
            <input type="date" name="date" value="{{ $filters['date'] }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
        </label>
        <label>
            <span class="mb-1 block text-xs text-slate-500">{{ __('Supervisor') }}</span>
            <select name="supervisor" @disabled($supervisorLocked) class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 disabled:opacity-60">
                <option value="">{{ __('All visible salesmen') }}</option>
                @foreach($supervisors as $supervisor)
                    <option value="{{ $supervisor->uuid }}" @selected($filters['supervisor'] === $supervisor->uuid)>{{ $supervisor->employee_code }} · {{ $supervisor->full_name }}</option>
                @endforeach
            </select>
            @if($supervisorLocked)
                <input type="hidden" name="supervisor" value="{{ $filters['supervisor'] }}">
            @endif
        </label>
        <button class="self-end rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400">{{ __('Apply') }}</button>
    </form>

    <div class="mb-5 flex flex-wrap gap-2 text-xs text-slate-400">
        <span>{{ $intelligence['date'] }}</span>
        <span>·</span>
        <span>{{ $intelligence['timezone'] }}</span>
        <span>·</span>
        <span>{{ __('Workday') }} {{ $intelligence['workday']['start'] }}–{{ $intelligence['workday']['end'] }}</span>
        <span>·</span>
        <span>{{ __('Expected route progress') }} {{ number_format($summary['expected_route_progress_percent'], 1) }}%</span>
        @if($intelligence['supervisor'])
            <span>·</span>
            <span>{{ __('Team') }}: {{ $intelligence['supervisor']->full_name }}</span>
        @endif
    </div>

    <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Visible salesmen') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['salesmen'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $summary['started'] }} {{ __('started') }} · {{ $summary['not_started'] }} {{ __('not started') }}</p>
        </div>
        <div class="rounded-2xl border {{ $summary['late_starts'] > 0 ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Late starts') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['late_starts'] }}</p>
        </div>
        <div class="rounded-2xl border {{ $summary['behind_routes'] > 0 ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Routes behind') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['behind_routes'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ number_format($summary['average_route_completion_percent'], 1) }}% {{ __('team completion') }}</p>
        </div>
        <div class="rounded-2xl border {{ $summary['missed_stops'] > 0 ? 'border-rose-400/20' : 'border-white/10' }} bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Missed visits') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['missed_stops'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $summary['off_route_visits'] }} {{ __('off-route visits') }}</p>
        </div>
        <div class="rounded-2xl border {{ ($summary['pending_collections'] ?? 0) > 0 ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Pending collections') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['pending_collections'] === null ? '—' : $summary['pending_collections'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $summary['pending_orders'] === null ? '—' : $summary['pending_orders'] }} {{ __('pending orders') }}</p>
        </div>
        <div class="rounded-2xl border {{ $summary['overdue_follow_ups'] > 0 ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Overdue follow-ups') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['overdue_follow_ups'] }}</p>
        </div>
        <div class="rounded-2xl border {{ $summary['below_target'] > 0 ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Below target') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['below_target'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Threshold') }} {{ $intelligence['thresholds']['target_attention_percent'] }}%</p>
        </div>
        <div class="rounded-2xl border {{ $summary['high_attention_salesmen'] > 0 ? 'border-rose-400/20' : ($summary['attention_salesmen'] > 0 ? 'border-amber-400/20' : 'border-white/10') }} bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Needs attention') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['attention_salesmen'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $summary['high_attention_salesmen'] }} {{ __('high attention') }}</p>
        </div>
    </section>

    <section class="mb-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ __('Daily management briefing') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Calculated from the same verified metrics shown below; no AI is required for these numbers.') }}</p>
                </div>
                <a href="{{ route('admin.ai-insights.index') }}" class="rounded-lg bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20">{{ __('Ask FieldPulse about this') }}</a>
            </div>

            @if($summary['attention_salesmen'] === 0)
                <div class="mt-4 rounded-xl border border-emerald-400/15 bg-emerald-500/5 p-4 text-sm text-emerald-200">{{ __('No current salesman crosses the configured management attention rules for this view.') }}</div>
            @else
                <p class="mt-4 text-sm leading-6 text-slate-300">
                    <strong>{{ $summary['attention_salesmen'] }}</strong> {{ __('salesmen need review') }}.
                    {{ __('Routes behind:') }} <strong>{{ $summary['behind_routes'] }}</strong>,
                    {{ __('missed visits:') }} <strong>{{ $summary['missed_stops'] }}</strong>,
                    {{ __('not started:') }} <strong>{{ $summary['not_started'] }}</strong>,
                    {{ __('below target:') }} <strong>{{ $summary['below_target'] }}</strong>.
                </p>
            @endif

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-950/70 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Planned stops') }}</p>
                    <p class="mt-1 text-lg font-bold">{{ $summary['assigned_stops'] }}</p>
                    <p class="text-xs text-slate-500">{{ $summary['visited_planned_stops'] }} {{ __('visited') }} · {{ $summary['remaining_stops'] }} {{ __('remaining') }}</p>
                </div>
                <div class="rounded-xl bg-slate-950/70 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Capacity risk') }}</p>
                    <p class="mt-1 text-lg font-bold">{{ $summary['capacity_risk_routes'] }}</p>
                    <p class="text-xs text-slate-500">{{ __('route(s) exceed planned capacity') }}</p>
                </div>
                <div class="rounded-xl bg-slate-950/70 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Visit flags') }}</p>
                    <p class="mt-1 text-lg font-bold">{{ $summary['unresolved_flags'] }}</p>
                    <p class="text-xs text-slate-500">{{ __('unresolved') }}</p>
                </div>
            </div>
        </div>

        <aside class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-4 py-3">
                <h2 class="font-semibold">{{ __('Priority review') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Ordered by the number and severity of verified exceptions.') }}</p>
            </div>
            <div class="max-h-[330px] divide-y divide-white/10 overflow-y-auto">
                @forelse($intelligence['briefing']['focus'] as $focus)
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">{{ $focus['salesman'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $focus['employee_code'] }}</p>
                            </div>
                            <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold {{ $levelClasses[$focus['attention_level']] ?? $levelClasses['watch'] }}">{{ __(str($focus['attention_level'])->title()->toString()) }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($focus['reasons'] as $reason)
                                <span class="rounded-full bg-white/5 px-2 py-1 text-[10px] text-slate-300">{{ $reasonLabels[$reason] ?? __(str($reason)->replace('_', ' ')->title()->toString()) }}</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-sm text-slate-500">{{ __('No current priority exceptions.') }}</div>
                @endforelse
            </div>
        </aside>
    </section>

    <div class="space-y-4 lg:hidden">
        @forelse($intelligence['rows'] as $row)
            <article class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $row['salesman']->full_name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $row['salesman']->employee_code }}</p>
                    </div>
                    <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold {{ $levelClasses[$row['attention_level']] ?? $levelClasses['clear'] }}">{{ __(str($row['attention_level'])->title()->toString()) }}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-slate-500">{{ __('Attendance') }}</p><p class="mt-1 font-semibold">{{ $row['attendance']['days'] > 0 ? __('Started') : __('Not started') }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['attendance']['late_starts'] }} {{ __('late start(s)') }}</p></div>
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-slate-500">{{ __('Route') }}</p><p class="mt-1 font-semibold">{{ number_format($row['route']['completion_percent'], 1) }}%</p><p class="mt-1 text-xs text-slate-500">{{ $row['route']['remaining_stops'] }} {{ __('remaining') }} · {{ $row['route']['missed_stops'] }} {{ __('missed') }}</p></div>
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-slate-500">{{ __('Orders') }}</p><p class="mt-1 font-semibold">{{ $row['orders']['count'] }} {{ __('approved') }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['pending_orders'] === null ? '—' : $row['pending_orders'].' '.__('pending') }}</p></div>
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-xs text-slate-500">{{ __('Collections') }}</p><p class="mt-1 font-semibold">{{ $row['collections']['count'] }} {{ __('verified') }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['pending_collections'] === null ? '—' : $row['pending_collections'].' '.__('pending') }}</p></div>
                </div>
                @if($row['attention_reasons'])
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach($row['attention_reasons'] as $reason)
                            <span class="rounded-full bg-white/5 px-2 py-1 text-[10px] text-slate-300">{{ $reasonLabels[$reason] ?? __(str($reason)->replace('_', ' ')->title()->toString()) }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-center text-slate-500">{{ __('No salesmen are visible for this management view.') }}</div>
        @endforelse
    </div>

    <section class="hidden overflow-hidden rounded-2xl border border-white/10 bg-slate-900 lg:block">
        <div class="overflow-x-auto">
            <table class="min-w-[1450px] w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Salesman') }}</th>
                        <th class="px-4 py-3">{{ __('Attention') }}</th>
                        <th class="px-4 py-3">{{ __('Attendance') }}</th>
                        <th class="px-4 py-3">{{ __('Route progress') }}</th>
                        <th class="px-4 py-3">{{ __('Visits') }}</th>
                        <th class="px-4 py-3">{{ __('Orders') }}</th>
                        <th class="px-4 py-3">{{ __('Collections') }}</th>
                        <th class="px-4 py-3">{{ __('Target') }}</th>
                        <th class="px-4 py-3">{{ __('Exceptions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($intelligence['rows'] as $row)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <p class="font-semibold">{{ $row['salesman']->full_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['salesman']->employee_code }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['assignment']?->territory?->name ?? $row['assignment']?->branch?->name ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $levelClasses[$row['attention_level']] ?? $levelClasses['clear'] }}">{{ __(str($row['attention_level'])->title()->toString()) }}</span>
                                <p class="mt-2 text-xs text-slate-500">{{ count($row['attention_reasons']) }} {{ __('reason(s)') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p>{{ $row['attendance']['days'] > 0 ? __('Started') : __('Not started') }}</p>
                                <p class="mt-1 text-xs {{ $row['attendance']['late_starts'] > 0 ? 'text-amber-300' : 'text-slate-500' }}">{{ $row['attendance']['late_starts'] }} {{ __('late') }} · {{ $row['attendance']['early_finishes'] }} {{ __('early') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold">{{ number_format($row['route']['completion_percent'], 1) }}%</p>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Expected') }} {{ number_format($row['expected_route_progress_percent'], 1) }}% · {{ $row['route']['remaining_stops'] }} {{ __('remaining') }}</p>
                                @if($row['route_behind'])<p class="mt-1 text-xs text-amber-300">{{ __('Behind by') }} {{ number_format(max(0, $row['route_progress_gap_percent']), 1) }} {{ __('points') }}</p>@endif
                            </td>
                            <td class="px-4 py-4">
                                <p>{{ $row['visits']['completed'] }} {{ __('completed') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['route']['missed_stops'] }} {{ __('missed') }} · {{ $row['route']['off_route_visits'] }} {{ __('off-route') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold">{{ $row['orders']['count'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $money($row['orders']['totals']) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['pending_orders'] === null ? '—' : $row['pending_orders'].' '.__('pending') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold">{{ $row['collections']['count'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $money($row['collections']['totals']) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['pending_collections'] === null ? '—' : $row['pending_collections'].' '.__('pending') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                @if($row['target_average_percent'] === null)
                                    <span class="text-slate-500">—</span>
                                @else
                                    <p class="font-semibold {{ $row['target_average_percent'] < $intelligence['thresholds']['target_attention_percent'] ? 'text-amber-300' : '' }}">{{ number_format($row['target_average_percent'], 1) }}%</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ count($row['targets']) }} {{ __('active target(s)') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <p>{{ $row['follow_ups']['overdue'] }} {{ __('overdue follow-up(s)') }}</p>
                                <p class="mt-1 text-xs {{ $row['unresolved_flags'] > 0 ? 'text-rose-300' : 'text-slate-500' }}">{{ $row['unresolved_flags'] }} {{ __('visit flag(s)') }}</p>
                                @if($row['attention_reasons'])
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-semibold text-indigo-300">{{ __('Why') }}</summary>
                                        <div class="mt-2 space-y-1">
                                            @foreach($row['attention_reasons'] as $reason)
                                                <p class="text-xs text-slate-400">{{ $reasonLabels[$reason] ?? __(str($reason)->replace('_', ' ')->title()->toString()) }}</p>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">{{ __('No salesmen are visible for this management view.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
