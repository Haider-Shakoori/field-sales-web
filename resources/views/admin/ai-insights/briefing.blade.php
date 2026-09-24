<x-layouts.app>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 text-lg font-black text-slate-950 shadow-lg shadow-orange-950/20">☀</div>
            <div>
                <h1 class="text-2xl font-bold">{{ __('Manager Morning Briefing') }}</h1>
                <p class="mt-0.5 text-sm text-slate-400">
                    {{ __('A grounded operational briefing generated from current FieldPulse data.') }}
                </p>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.ai-insights.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-white/5">
            {{ __('Back to Ask FieldPulse') }}
        </a>
        <button type="button" onclick="window.print()" class="rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold transition hover:bg-indigo-400">
            {{ __('Print briefing') }}
        </button>
    </div>
</div>

<section class="mb-6 rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900 to-slate-950 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-300">{{ __('Briefing date') }}</p>
            <p class="mt-1 text-xl font-bold">{{ $briefing['today'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $briefing['timezone'] }} · {{ __('Generated') }} {{ $briefing['generated_time'] }}</p>
        </div>
        <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ __('Live FieldPulse data · read-only analysis') }}
        </div>
    </div>
</section>

<div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Yesterday commercial results') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $briefing['yesterday'] }}</p>
            </div>
            <div class="rounded-xl bg-slate-950 px-4 py-3 text-right">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Visits') }}</p>
                <p class="mt-1 text-2xl font-bold">{{ $briefing['yesterday_visits'] }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl bg-slate-950 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Approved sales') }}</p>
                <div class="mt-3 space-y-2">
                    @forelse($briefing['yesterday_sales'] as $row)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $row['currency'] }} {{ number_format($row['total'], 2) }}</p>
                                <p class="text-xs text-slate-500">{{ trans_choice(':count approved order|:count approved orders', $row['count'], ['count' => $row['count']]) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('No approved sales recorded yesterday.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl bg-slate-950 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Verified collections') }}</p>
                <div class="mt-3 space-y-2">
                    @forelse($briefing['yesterday_collections'] as $row)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-emerald-300">{{ $row['currency'] }} {{ number_format($row['total'], 2) }}</p>
                                <p class="text-xs text-slate-500">{{ trans_choice(':count verified collection|:count verified collections', $row['count'], ['count' => $row['count']]) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('No verified collections recorded yesterday.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">{{ __('Today field readiness') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Attendance and work-session readiness for active salesmen.') }}</p>

        <div class="mt-4 grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-slate-950 p-4 text-center">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Active') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $briefing['today_attendance']['active_salesmen'] }}</p>
            </div>
            <div class="rounded-xl bg-slate-950 p-4 text-center">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Started') }}</p>
                <p class="mt-2 text-2xl font-bold text-emerald-300">{{ $briefing['today_attendance']['started'] }}</p>
            </div>
            <div class="rounded-xl bg-slate-950 p-4 text-center">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Not started') }}</p>
                <p class="mt-2 text-2xl font-bold {{ $briefing['today_attendance']['not_started'] > 0 ? 'text-amber-300' : 'text-emerald-300' }}">{{ $briefing['today_attendance']['not_started'] }}</p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach([
                ['label' => __('Orders'), 'value' => $briefing['pending']['orders']],
                ['label' => __('Collections'), 'value' => $briefing['pending']['collections']],
                ['label' => __('Expenses'), 'value' => $briefing['pending']['expenses']],
                ['label' => __('Returns'), 'value' => $briefing['pending']['returns']],
            ] as $pending)
                <div class="rounded-xl border border-white/10 p-3 text-center">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ $pending['label'] }}</p>
                    <p class="mt-1 text-xl font-bold">{{ $pending['value'] === null ? '—' : $pending['value'] }}</p>
                    <p class="mt-1 text-[10px] text-slate-600">{{ __('Pending') }}</p>
                </div>
            @endforeach
        </div>
    </section>
</div>

<section class="mt-6 rounded-2xl border border-white/10 bg-slate-900 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ __('Management exceptions') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Exceptions that should be reviewed before normal daily work continues.') }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-2">
        <div class="rounded-xl border border-rose-400/15 bg-rose-500/5 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Overdue follow-ups') }}</p>
            <p class="mt-2 text-3xl font-bold {{ ($briefing['exceptions']['overdue_followups'] ?? 0) > 0 ? 'text-rose-300' : '' }}">
                {{ $briefing['exceptions']['overdue_followups'] === null ? '—' : $briefing['exceptions']['overdue_followups'] }}
            </p>
        </div>
        <div class="rounded-xl border border-amber-400/15 bg-amber-500/5 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Unreviewed suspicious visit flags') }}</p>
            <p class="mt-2 text-3xl font-bold {{ ($briefing['exceptions']['unreviewed_visit_flags'] ?? 0) > 0 ? 'text-amber-300' : '' }}">
                {{ $briefing['exceptions']['unreviewed_visit_flags'] === null ? '—' : $briefing['exceptions']['unreviewed_visit_flags'] }}
            </p>
        </div>
    </div>
</section>

<section class="mt-6 rounded-2xl border border-white/10 bg-slate-900 p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ __('Today priorities') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Ranked from grounded FieldPulse evidence, not model guesswork.') }}</p>
        </div>
        <a href="{{ route('admin.ai-insights.index') }}" class="text-sm font-semibold text-indigo-300 hover:text-indigo-200">{{ __('Discuss with Ask FieldPulse') }} →</a>
    </div>

    <div class="space-y-3">
        @forelse($briefing['priorities'] as $index => $recommendation)
            @php
                $tone = match($recommendation['severity']) {
                    'critical' => 'border-rose-400/30 bg-rose-500/10',
                    'high' => 'border-orange-400/20 bg-orange-500/10',
                    'medium' => 'border-amber-400/20 bg-amber-500/10',
                    default => 'border-emerald-400/20 bg-emerald-500/10',
                };
            @endphp
            <article class="rounded-xl border p-4 {{ $tone }}">
                <div class="flex items-start gap-4">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-black/20 text-sm font-black">{{ $index + 1 }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold">{{ __($recommendation['title']) }}</h3>
                            <span class="rounded-full bg-black/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">{{ __(str($recommendation['severity'])->title()->toString()) }}</span>
                            <span class="rounded-full bg-black/20 px-2 py-0.5 text-[10px] text-slate-400">{{ __('Priority score') }} {{ $recommendation['score'] }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-200">{{ __($recommendation['message'], $recommendation['message_params'] ?? []) }}</p>
                        <p class="mt-2 text-sm text-slate-400">{{ __($recommendation['action']) }}</p>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-xl bg-slate-950 p-6 text-center text-sm text-slate-500">{{ __('No urgent management priorities detected.') }}</div>
        @endforelse
    </div>
</section>
</x-layouts.app>
