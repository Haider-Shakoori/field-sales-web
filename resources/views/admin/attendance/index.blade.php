<x-layouts.app>
    @php
        $formatMinutes = static function (?int $minutes): string {
            if ($minutes === null) {
                return '—';
            }

            $minutes = max(0, $minutes);

            return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
        };

        $todayStatus = static function ($session): array {
            if (! $session) {
                return ['No record', 'bg-slate-500/10 text-slate-300'];
            }

            if ($session->status === 'active') {
                return ['Working', 'bg-emerald-500/10 text-emerald-300'];
            }

            if ($session->status === 'completed') {
                return ['Completed', 'bg-sky-500/10 text-sky-300'];
            }

            return [str($session->status)->replace('_', ' ')->title(), 'bg-amber-500/10 text-amber-300'];
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Attendance & work hours</h1>
            <p class="mt-1 text-sm text-slate-400">
                Review daily attendance and total worked time for every salesman. Times are shown in {{ $timezone }}.
            </p>
        </div>
        <a href="{{ route('admin.salesmen.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold hover:bg-white/20">
            Salesmen
        </a>
    </div>

    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-white/10 bg-slate-900 p-5 md:grid-cols-4">
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">From</span>
            <input
                type="date"
                name="date_from"
                value="{{ $filters['date_from'] }}"
                class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm"
            >
        </label>

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">To</span>
            <input
                type="date"
                name="date_to"
                value="{{ $filters['date_to'] }}"
                class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm"
            >
        </label>

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Salesman</span>
            <select name="salesman" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                <option value="">All salesmen</option>
                @foreach($salesmanOptions as $salesman)
                    <option value="{{ $salesman->uuid }}" @selected($filters['salesman'] === $salesman->uuid)>
                        {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                    </option>
                @endforeach
            </select>
        </label>

        <div class="flex items-end gap-2">
            <button class="flex-1 rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400">
                Apply
            </button>
            <a href="{{ route('admin.attendance.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold hover:bg-white/20">
                Reset
            </a>
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Salesmen</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['salesmen'] }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Attendance days</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['attendance_days'] }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total worked</p>
            <p class="mt-2 text-2xl font-bold">{{ $formatMinutes($summary['total_minutes']) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Working now</p>
            <p class="mt-2 text-2xl font-bold">{{ $summary['active_now'] }}</p>
        </div>
    </div>

    <section class="mb-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">Salesman attendance summary</h2>
            <p class="mt-1 text-sm text-slate-400">{{ $filters['date_from'] }} → {{ $filters['date_to'] }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300">
                <tr>
                    <th class="px-5 py-3">Salesman</th>
                    <th class="px-5 py-3">Today</th>
                    <th class="px-5 py-3">Days attended</th>
                    <th class="px-5 py-3">Total worked</th>
                    <th class="px-5 py-3">Average / day</th>
                    <th class="px-5 py-3">Late starts</th>
                    <th class="px-5 py-3">Early finishes</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($attendanceRows as $row)
                    @php([$statusLabel, $statusClass] = $todayStatus($row['today_session']))
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.salesmen.show', $row['salesman']) }}" class="font-medium hover:text-indigo-300">
                                {{ $row['salesman']->full_name }}
                            </a>
                            <div class="mt-1 text-xs text-slate-400">
                                {{ $row['salesman']->employee_code }}
                                @if($row['salesman']->user?->branch)
                                    · {{ $row['salesman']->user->branch->name }}
                                @endif
                                @unless($row['salesman']->is_active)
                                    · Inactive
                                @endunless
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-5 py-4">{{ $row['attendance_days'] }}</td>
                        <td class="px-5 py-4 font-semibold">{{ $formatMinutes($row['total_minutes']) }}</td>
                        <td class="px-5 py-4">{{ $formatMinutes($row['average_minutes']) }}</td>
                        <td class="px-5 py-4">{{ $row['late_starts'] }}</td>
                        <td class="px-5 py-4">{{ $row['early_finishes'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-slate-400">No salesman profiles found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">Daily attendance records</h2>
            <p class="mt-1 text-sm text-slate-400">Start/end times and recorded work duration for the selected period.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300">
                <tr>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Salesman</th>
                    <th class="px-5 py-3">Start</th>
                    <th class="px-5 py-3">End</th>
                    <th class="px-5 py-3">Worked</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Flags</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($sessions as $session)
                    <tr>
                        <td class="px-5 py-4">{{ $session->date?->toDateString() }}</td>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $session->salesman?->full_name ?? 'Unknown salesman' }}</div>
                            <div class="text-xs text-slate-400">{{ $session->salesman?->employee_code }}</div>
                        </td>
                        <td class="px-5 py-4">{{ $session->start_time?->copy()->setTimezone($timezone)->format('H:i') ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $session->end_time?->copy()->setTimezone($timezone)->format('H:i') ?? '—' }}</td>
                        <td class="px-5 py-4 font-semibold">{{ $formatMinutes($session->display_duration_minutes) }}</td>
                        <td class="px-5 py-4">
                            @if($session->status === 'active')
                                <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">Working</span>
                            @elseif($session->status === 'completed')
                                <span class="rounded-full bg-sky-500/10 px-2.5 py-1 text-xs font-semibold text-sky-300">Completed</span>
                            @else
                                <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-300">
                                    {{ str($session->status)->replace('_', ' ')->title() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                @if($session->is_late_start)
                                    <span class="rounded-full bg-amber-500/10 px-2 py-1 text-xs text-amber-300">Late start</span>
                                @endif
                                @if($session->is_early_finish)
                                    <span class="rounded-full bg-rose-500/10 px-2 py-1 text-xs text-rose-300">Early finish</span>
                                @endif
                                @unless($session->is_late_start || $session->is_early_finish)
                                    <span class="text-xs text-slate-500">—</span>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-slate-400">No attendance records for this period.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-5">{{ $sessions->links() }}</div>
</x-layouts.app>
