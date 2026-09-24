<x-layouts.app>
@php
    $previousMonth = $month->subMonth()->format('Y-m');
    $nextMonth = $month->addMonth()->format('Y-m');
    $today = now($timezone)->toDateString();
    $days = $gridStart->diffInDays($gridEnd) + 1;
    $dayHeaders = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];
@endphp

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ __('Calendar & appointments') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Plan customer meetings, calls, visits and field commitments in one shared calendar.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.daily-planner.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Daily planner') }}</a>
        <a href="{{ route('admin.follow-ups.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Follow-ups') }}</a>
    </div>
</div>

@if(session('status'))
    <div class="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
@endif

<div class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="min-w-0 space-y-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.appointments.index', array_filter(['month' => $previousMonth, 'salesman' => $filters['salesman'], 'status' => $filters['status']])) }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 hover:bg-white/5" aria-label="{{ __('Previous month') }}">←</a>
                    <div class="min-w-[190px] text-center">
                        <p class="text-lg font-semibold">{{ $month->translatedFormat('F Y') }}</p>
                        <a href="{{ route('admin.appointments.index') }}" class="text-xs text-indigo-300 hover:text-indigo-200">{{ __('Today') }}</a>
                    </div>
                    <a href="{{ route('admin.appointments.index', array_filter(['month' => $nextMonth, 'salesman' => $filters['salesman'], 'status' => $filters['status']])) }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 hover:bg-white/5" aria-label="{{ __('Next month') }}">→</a>
                </div>

                <form method="GET" class="flex flex-wrap gap-2">
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <select name="salesman" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                        <option value="">{{ __('All salesmen') }}</option>
                        @foreach($salesmen as $salesman)
                            <option value="{{ $salesman->uuid }}" @selected($filters['salesman'] === $salesman->uuid)>{{ $salesman->employee_code }} · {{ $salesman->full_name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach($appointmentStatuses as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __(str($status)->title()->toString()) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl bg-indigo-500 px-4 py-2 text-sm font-semibold hover:bg-indigo-400">{{ __('Filter') }}</button>
                </form>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="hidden grid-cols-7 border-b border-white/10 bg-white/[0.03] text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500 md:grid">
                @foreach($dayHeaders as $header)
                    <div class="px-2 py-3">{{ $header }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 divide-y divide-white/10 md:grid-cols-7 md:divide-x md:divide-y-0">
                @for($i = 0; $i < $days; $i++)
                    @php
                        $date = $gridStart->addDays($i);
                        $dateKey = $date->toDateString();
                        $rows = $groupedAppointments->get($dateKey, collect());
                        $inMonth = $date->month === $month->month;
                        $isToday = $dateKey === $today;
                    @endphp
                    <div class="min-h-[132px] border-white/10 p-2 {{ !$inMonth ? 'bg-slate-950/40 text-slate-600' : '' }} {{ $i >= 7 ? 'md:border-t' : '' }}">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="flex h-7 min-w-7 items-center justify-center rounded-lg px-1 text-xs font-semibold {{ $isToday ? 'bg-indigo-500 text-white' : '' }}">{{ $date->day }}</span>
                            @if($rows->count() > 3)<span class="text-[10px] text-slate-500">+{{ $rows->count() - 3 }}</span>@endif
                        </div>
                        <div class="space-y-1.5">
                            @foreach($rows->take(3) as $appointment)
                                @php
                                    $local = $appointment->starts_at->copy()->setTimezone($timezone);
                                    $tone = match($appointment->status) {
                                        'completed' => 'border-emerald-400/20 bg-emerald-500/10 text-emerald-200',
                                        'cancelled' => 'border-slate-600/30 bg-slate-800/60 text-slate-500',
                                        default => $appointment->starts_at->isPast() ? 'border-rose-400/20 bg-rose-500/10 text-rose-200' : 'border-indigo-400/20 bg-indigo-500/10 text-indigo-100',
                                    };
                                @endphp
                                <a href="#appointment-{{ $appointment->uuid }}" class="block rounded-lg border px-2 py-1.5 text-[11px] leading-4 {{ $tone }}">
                                    <div class="truncate font-semibold">{{ $local->format('H:i') }} · {{ $appointment->title }}</div>
                                    <div class="truncate opacity-70">{{ $appointment->customer?->name ?? $appointment->assignedSalesman?->full_name ?? __('Unassigned') }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        <section class="rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4">
                <h2 class="font-semibold">{{ __('Month agenda') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Detailed schedule for the selected calendar range.') }}</p>
            </div>
            <div class="divide-y divide-white/10">
                @forelse($appointments as $appointment)
                    @php
                        $startLocal = $appointment->starts_at->copy()->setTimezone($timezone);
                        $endLocal = $appointment->ends_at?->copy()->setTimezone($timezone);
                    @endphp
                    <article id="appointment-{{ $appointment->uuid }}" class="p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-slate-100">{{ $appointment->title }}</h3>
                                    <span class="rounded-full bg-white/5 px-2.5 py-1 text-[10px] uppercase tracking-wide text-slate-400">{{ __(str($appointment->type)->title()->toString()) }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $appointment->status === 'completed' ? 'bg-emerald-500/10 text-emerald-300' : ($appointment->status === 'cancelled' ? 'bg-slate-800 text-slate-500' : 'bg-indigo-500/10 text-indigo-300') }}">{{ __(str($appointment->status)->title()->toString()) }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-300">{{ $startLocal->format('D, M j · H:i') }}@if($endLocal) – {{ $endLocal->format($endLocal->toDateString() === $startLocal->toDateString() ? 'H:i' : 'M j H:i') }}@endif</p>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    @if($appointment->customer)<a href="{{ route('admin.customers.show', $appointment->customer) }}" class="hover:text-indigo-300">{{ $appointment->customer->name }}</a>@endif
                                    @if($appointment->assignedSalesman)<span>{{ $appointment->assignedSalesman->employee_code }} · {{ $appointment->assignedSalesman->full_name }}</span>@endif
                                    @if($appointment->location)<span>📍 {{ $appointment->location }}</span>@endif
                                    @if($appointment->reminder_minutes_before !== null)<span>🔔 {{ $appointment->reminder_minutes_before === 1440 ? __('1 day before') : __(':minutes min before', ['minutes' => $appointment->reminder_minutes_before]) }}</span>@endif
                                </div>
                                @if($appointment->notes)<p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-slate-400">{{ $appointment->notes }}</p>@endif
                            </div>

                            @if($canManage)
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    @if($appointment->status === 'scheduled')
                                        <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="rounded-lg bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/20">{{ __('Complete') }}</button></form>
                                        <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="rounded-lg bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-300 hover:bg-rose-500/20">{{ __('Cancel') }}</button></form>
                                    @else
                                        <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="scheduled"><button class="rounded-lg bg-white/5 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/10">{{ __('Reopen') }}</button></form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-slate-500">{{ __('No appointments in this calendar range.') }}</div>
                @endforelse
            </div>
        </section>
    </section>

    @if($canManage)
        <aside class="self-start rounded-2xl border border-white/10 bg-slate-900 p-5 2xl:sticky 2xl:top-6">
            <h2 class="text-lg font-semibold">{{ __('Schedule appointment') }}</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('Create a commitment that appears in the assigned salesman’s FieldPulse calendar.') }}</p>

            <form method="POST" action="{{ route('admin.appointments.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Title') }}</label>
                    <input name="title" value="{{ old('title') }}" required maxlength="160" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('e.g. Payment collection meeting') }}">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Type') }}</label>
                        <select name="type" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                            @foreach($appointmentTypes as $type)<option value="{{ $type }}" @selected(old('type', 'meeting') === $type)>{{ __(str($type)->title()->toString()) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Reminder') }}</label>
                        <select name="reminder_minutes_before" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                            <option value="">{{ __('None') }}</option>
                            @foreach([0 => __('At start time'), 15 => __('15 minutes'), 30 => __('30 minutes'), 60 => __('1 hour'), 120 => __('2 hours'), 1440 => __('1 day')] as $minutes => $label)
                                <option value="{{ $minutes }}" @selected((string) old('reminder_minutes_before') === (string) $minutes)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Customer') }}</label>
                    <select name="customer_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                        <option value="">{{ __('No customer') }}</option>
                        @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->code }} · {{ $customer->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Assigned salesman') }}</label>
                    <select name="assigned_salesman_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                        @if(auth()->user()->hasAnyRole(['owner', 'company_admin', 'sales_manager', 'auditor']))<option value="">{{ __('Unassigned') }}</option>@endif
                        @foreach($salesmen as $salesman)<option value="{{ $salesman->id }}" @selected((string) old('assigned_salesman_id') === (string) $salesman->id)>{{ $salesman->employee_code }} · {{ $salesman->full_name }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Starts') }}</label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $month->isCurrentMonth() ? now($timezone)->addHour()->format('Y-m-d\TH:i') : $month->setTime(9, 0)->format('Y-m-d\TH:i')) }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Ends') }}</label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Location') }}</label>
                    <input name="location" value="{{ old('location') }}" maxlength="255" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Office, customer site, online…') }}">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="4" maxlength="5000" class="w-full resize-y rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">{{ old('notes') }}</textarea>
                </div>
                <button class="w-full rounded-xl bg-indigo-500 px-4 py-3 font-semibold text-white shadow-lg shadow-indigo-950/20 hover:bg-indigo-400">{{ __('Schedule appointment') }}</button>
            </form>
        </aside>
    @endif
</div>
</x-layouts.app>
