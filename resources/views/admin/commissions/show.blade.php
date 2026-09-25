<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.commissions.index') }}" class="text-xs font-semibold text-indigo-300 hover:text-indigo-200">← {{ __('Commissions') }}</a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ __('Commission run') }} · {{ $run->period_start?->toDateString() }} → {{ $run->period_end?->toDateString() }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full px-2.5 py-1 {{ $run->state === 'approved' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300' }}">{{ __(str($run->state)->title()->toString()) }}</span>
                <span class="rounded-full bg-white/5 px-2.5 py-1 text-slate-400">{{ $run->lines->count() }} {{ __('lines') }}</span>
                <span class="text-slate-500">{{ __('Generated') }} {{ $run->generated_at?->format('Y-m-d H:i') }} · {{ $run->generator?->name ?? '—' }}</span>
            </div>
        </div>
        @if($canManage && $run->state === 'draft')
            <form method="POST" action="{{ route('admin.commissions.runs.approve', $run) }}">
                @csrf
                <button class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-emerald-400">{{ __('Approve & lock run') }}</button>
            </form>
        @endif
    </div>

    @if($run->state === 'approved')
        <div class="mb-5 rounded-2xl border border-emerald-400/20 bg-emerald-500/5 p-4 text-sm text-emerald-100">
            {{ __('This run is approved and immutable.') }}
            @if($run->approved_at) {{ __('Approved') }} {{ $run->approved_at->format('Y-m-d H:i') }} · {{ $run->approver?->name ?? '—' }}. @endif
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-amber-400/20 bg-amber-500/5 p-4 text-sm text-amber-100">{{ __('This is a draft. Regenerating the same exact period recalculates all lines from current rules and approved source data.') }}</div>
    @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @forelse($totals as $total)
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <div class="flex items-center justify-between gap-2"><p class="text-xs uppercase tracking-wide text-slate-500">{{ $total['currency'] }}</p><span class="rounded-full bg-white/5 px-2 py-1 text-[10px] text-slate-500">{{ $total['lines'] }} {{ __('lines') }}</span></div>
                <p class="mt-2 text-2xl font-bold text-emerald-300">{{ number_format($total['commission'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __('Commission total') }}</p>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-white/10 p-6 text-sm text-slate-500 sm:col-span-2 xl:col-span-4">{{ __('No commission lines were produced. Check rule effective dates, scopes, minimum basis, and source data.') }}</div>
        @endforelse
    </div>

    <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 px-5 py-4">
            <div><h2 class="font-semibold">{{ __('Calculation lines') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('Each row is reproducible from its rule, source basis, rate, and evidence. Currency totals are intentionally separate.') }}</p></div>
            @if($run->notes)<div class="max-w-xl rounded-lg bg-white/5 px-3 py-2 text-xs text-slate-400">{{ $run->notes }}</div>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">{{ __('Salesman') }}</th><th class="px-4 py-3">{{ __('Rule') }}</th><th class="px-4 py-3">{{ __('Basis') }}</th><th class="px-4 py-3">{{ __('Rate') }}</th><th class="px-4 py-3">{{ __('Commission') }}</th><th class="px-4 py-3">{{ __('Evidence') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($run->lines as $line)
                        <tr>
                            <td class="px-4 py-4"><p class="font-semibold">{{ $line->salesman?->full_name ?? '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ $line->salesman?->employee_code }}</p></td>
                            <td class="px-4 py-4"><p>{{ $line->rule?->name ?? '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ $line->rule?->code }} · {{ __(str($line->basis_type)->replace('_', ' ')->title()->toString()) }}</p></td>
                            <td class="px-4 py-4"><p class="font-semibold">{{ $line->currency }} {{ number_format((float) $line->basis_value, 2) }}</p><p class="mt-1 text-xs text-slate-500">{{ __('Source basis') }}</p></td>
                            <td class="px-4 py-4">@if($line->reward_type === 'percentage')<span>{{ number_format((float) $line->rate, 2) }}%</span>@else<span>{{ $line->currency }} {{ number_format((float) $line->rate, 2) }}</span>@endif<p class="mt-1 text-xs text-slate-500">{{ __(str($line->reward_type)->title()->toString()) }}</p></td>
                            <td class="px-4 py-4"><span class="font-semibold text-emerald-300">{{ $line->currency }} {{ number_format((float) $line->commission_amount, 2) }}</span></td>
                            <td class="px-4 py-4">
                                @if($line->evidence)
                                    <details><summary class="cursor-pointer text-xs font-semibold text-indigo-300">{{ __('View evidence') }}</summary><pre class="mt-2 max-w-lg overflow-x-auto rounded-lg bg-slate-950 p-3 text-[11px] leading-5 text-slate-400">{{ json_encode($line->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                                @else
                                    <span class="text-xs text-slate-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">{{ __('No calculation lines in this run.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="font-semibold">{{ __('Commission controls') }}</h2>
        <div class="mt-3 grid gap-3 text-xs leading-5 text-slate-400 md:grid-cols-3">
            <p>{{ __('Approved orders and verified collections are the only monetary transaction sources.') }}</p>
            <p>{{ __('Percentage commissions never convert or combine currencies; AFN, USD, and other currencies stay separate.') }}</p>
            <p>{{ __('Matching rules can stack. Approved periods are locked and overlapping approved periods are blocked.') }}</p>
        </div>
    </div>
</x-layouts.app>
