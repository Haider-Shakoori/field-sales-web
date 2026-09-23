<x-layouts.app>
    @php
        $minutes = static function (int $value): string {
            $value = max(0, $value);

            return sprintf('%dh %02dm', intdiv($value, 60), $value % 60);
        };

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
            <h1 class="text-2xl font-bold">{{ __('Supervisor scorecards') }}</h1>
            <p class="mt-1 text-sm text-slate-400">
                {{ __('Transparent team KPIs from attendance, visits, orders, collections, follow-ups, targets, and visit-review flags.') }}
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold hover:bg-white/20">
            {{ __('Dashboard') }}
        </a>
    </div>

    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-white/10 bg-slate-900 p-5 md:grid-cols-4">
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('From') }}</span>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('To') }}</span>
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Supervisor') }}</span>
            <select name="supervisor" @disabled($supervisorLocked) class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm disabled:opacity-60">
                <option value="">{{ __('All visible salesmen') }}</option>
                @foreach($supervisors as $supervisor)
                    <option value="{{ $supervisor->uuid }}" @selected($filters['supervisor'] === $supervisor->uuid)>
                        {{ $supervisor->employee_code }} · {{ $supervisor->full_name }}
                    </option>
                @endforeach
            </select>
            @if($supervisorLocked)
                <input type="hidden" name="supervisor" value="{{ $filters['supervisor'] }}">
            @endif
        </label>
        <div class="flex items-end">
            <button class="w-full rounded-xl bg-sky-500 px-4 py-2.5 font-semibold text-slate-950 hover:bg-sky-400">{{ __('Apply') }}</button>
        </div>
    </form>

    <div class="mb-6 flex flex-wrap items-center gap-2 text-xs text-slate-400">
        <span>{{ $scorecard['period']['from'] }} → {{ $scorecard['period']['to'] }}</span>
        <span>·</span>
        <span>{{ $scorecard['period']['timezone'] }}</span>
        @if($scorecard['supervisor'])
            <span>·</span>
            <span>{{ __('Team') }}: {{ $scorecard['supervisor']->full_name }}</span>
        @endif
    </div>

    <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Salesmen') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $scorecard['summary']['salesmen'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $scorecard['summary']['attendance_days'] }} {{ __('attendance days') }} · {{ $minutes($scorecard['summary']['worked_minutes']) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Visits') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $scorecard['summary']['completed_visits'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $scorecard['summary']['productive_visits'] }} {{ __('order/collection outcomes') }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Approved orders') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $scorecard['summary']['approved_orders'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $money($scorecard['summary']['order_totals']) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Verified collections') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $scorecard['summary']['verified_collections'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $money($scorecard['summary']['collection_totals']) }}</p>
        </div>
    </section>

    <section class="mb-6 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-amber-400/15 bg-amber-500/5 p-4">
            <p class="text-xs uppercase tracking-wide text-amber-300/80">{{ __('Overdue follow-ups') }}</p>
            <p class="mt-2 text-xl font-bold text-amber-200">{{ $scorecard['summary']['overdue_follow_ups'] }}</p>
        </div>
        <div class="rounded-2xl border border-rose-400/15 bg-rose-500/5 p-4">
            <p class="text-xs uppercase tracking-wide text-rose-300/80">{{ __('Unresolved visit flags') }}</p>
            <p class="mt-2 text-xl font-bold text-rose-200">{{ $scorecard['summary']['unresolved_flags'] }}</p>
        </div>
    </section>

    <div class="space-y-4 lg:hidden">
        @forelse($scorecard['rows'] as $row)
            <article class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $row['salesman']->full_name }}</h2>
                        <p class="text-xs text-slate-400">{{ $row['salesman']->employee_code }}</p>
                    </div>
                    @if($row['target_average_percent'] !== null)
                        <span class="rounded-full bg-indigo-500/10 px-2.5 py-1 text-xs font-semibold text-indigo-200">
                            {{ number_format($row['target_average_percent'], 1) }}% {{ __('target avg') }}
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-white/5 p-3">
                        <p class="text-xs text-slate-500">{{ __('Attendance') }}</p>
                        <p class="mt-1 font-semibold">{{ $row['attendance']['days'] }} {{ __('days') }} · {{ $minutes($row['attendance']['minutes']) }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $row['attendance']['late_starts'] }} {{ __('late') }} · {{ $row['attendance']['early_finishes'] }} {{ __('early') }}</p>
                    </div>
                    <div class="rounded-xl bg-white/5 p-3">
                        <p class="text-xs text-slate-500">{{ __('Visits') }}</p>
                        <p class="mt-1 font-semibold">{{ $row['visits']['completed'] }} {{ __('completed') }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $row['visits']['productive'] }} {{ __('productive') }} · {{ $row['visits']['average_minutes'] }}m {{ __('avg') }}</p>
                    </div>
                    <div class="rounded-xl bg-white/5 p-3">
                        <p class="text-xs text-slate-500">{{ __('Orders') }}</p>
                        <p class="mt-1 font-semibold">{{ $row['orders']['count'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $money($row['orders']['totals']) }}</p>
                    </div>
                    <div class="rounded-xl bg-white/5 p-3">
                        <p class="text-xs text-slate-500">{{ __('Collections') }}</p>
                        <p class="mt-1 font-semibold">{{ $row['collections']['count'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $money($row['collections']['totals']) }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-amber-200">{{ $row['follow_ups']['overdue'] }} {{ __('overdue follow-ups') }}</span>
                    <span class="rounded-full bg-rose-500/10 px-2.5 py-1 text-rose-200">{{ $row['unresolved_flags'] }} {{ __('visit flags') }}</span>
                    @if($row['assignment']?->supervisor)
                        <span class="rounded-full bg-white/5 px-2.5 py-1 text-slate-300">{{ $row['assignment']->supervisor->full_name }}</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-center text-slate-400">{{ __('No salesmen match this scorecard period.') }}</div>
        @endforelse
    </div>

    <section class="hidden overflow-hidden rounded-2xl border border-white/10 bg-slate-900 lg:block">
        <div class="overflow-x-auto">
            <table class="min-w-[1200px] w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-5 py-3">{{ __('Salesman') }}</th>
                        <th class="px-5 py-3">{{ __('Assignment') }}</th>
                        <th class="px-5 py-3">{{ __('Attendance') }}</th>
                        <th class="px-5 py-3">{{ __('Visits') }}</th>
                        <th class="px-5 py-3">{{ __('Orders') }}</th>
                        <th class="px-5 py-3">{{ __('Collections') }}</th>
                        <th class="px-5 py-3">{{ __('Targets') }}</th>
                        <th class="px-5 py-3">{{ __('Follow-ups') }}</th>
                        <th class="px-5 py-3">{{ __('Flags') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($scorecard['rows'] as $row)
                        <tr class="align-top">
                            <td class="px-5 py-4">
                                <div class="font-medium">{{ $row['salesman']->full_name }}</div>
                                <div class="text-xs text-slate-400">{{ $row['salesman']->employee_code }}</div>
                            </td>
                            <td class="px-5 py-4 text-xs">
                                <div>{{ $row['assignment']?->supervisor?->full_name ?? '—' }}</div>
                                <div class="mt-1 text-slate-500">{{ $row['assignment']?->territory?->name ?? $row['assignment']?->branch?->name ?? '—' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div>{{ $row['attendance']['days'] }} {{ __('days') }} · {{ $minutes($row['attendance']['minutes']) }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $row['attendance']['late_starts'] }} {{ __('late') }} · {{ $row['attendance']['early_finishes'] }} {{ __('early') }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div>{{ $row['visits']['completed'] }} {{ __('completed') }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $row['visits']['productive'] }} {{ __('productive') }} · {{ $row['visits']['average_minutes'] }}m {{ __('avg') }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold">{{ $row['orders']['count'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $money($row['orders']['totals']) }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold">{{ $row['collections']['count'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $money($row['collections']['totals']) }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if($row['target_average_percent'] === null)
                                    <span class="text-slate-500">—</span>
                                @else
                                    <div class="font-semibold">{{ number_format($row['target_average_percent'], 1) }}%</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ count($row['targets']) }} {{ __('active targets') }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div>{{ $row['follow_ups']['pending'] }} {{ __('pending') }}</div>
                                <div class="mt-1 text-xs {{ $row['follow_ups']['overdue'] > 0 ? 'text-amber-300' : 'text-slate-500' }}">{{ $row['follow_ups']['overdue'] }} {{ __('overdue') }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="{{ $row['unresolved_flags'] > 0 ? 'text-rose-300' : 'text-slate-500' }}">{{ $row['unresolved_flags'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-slate-400">{{ __('No salesmen match this scorecard period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
