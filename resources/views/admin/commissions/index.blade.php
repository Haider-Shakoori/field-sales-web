<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ __('Commissions') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-400">{{ __('Configure transparent commission rules, generate reviewable draft periods, and lock approved payouts without mixing currencies.') }}</p>
        </div>
        @if($canManage)
            <a href="{{ route('admin.commissions.rules.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400">{{ __('New commission rule') }}</a>
        @endif
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Active rules') }}</p><p class="mt-2 text-2xl font-bold">{{ $rules->getCollection()->where('is_active', true)->count() }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Rules shown') }}</p><p class="mt-2 text-2xl font-bold">{{ $rules->total() }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Draft runs') }}</p><p class="mt-2 text-2xl font-bold text-amber-300">{{ $runs->getCollection()->where('state', 'draft')->count() }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Approved runs') }}</p><p class="mt-2 text-2xl font-bold text-emerald-300">{{ $runs->getCollection()->where('state', 'approved')->count() }}</p></div>
    </div>

    @if($canManage)
        <section class="mb-5 rounded-2xl border border-indigo-400/20 bg-indigo-500/5 p-5">
            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_520px]">
                <div>
                    <h2 class="font-semibold">{{ __('Generate commission draft') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">{{ __('Drafts can be regenerated before approval. Approved periods are immutable and overlapping approved periods are blocked.') }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Matching rules stack intentionally. Percentage results remain in each source currency; fixed rewards always use the rule currency.') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.commissions.runs.generate') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label><span class="mb-1 block text-xs text-slate-500">{{ __('Period start') }}</span><input required type="date" name="period_start" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></label>
                    <label><span class="mb-1 block text-xs text-slate-500">{{ __('Period end') }}</span><input required type="date" name="period_end" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></label>
                    <label class="sm:col-span-2"><span class="mb-1 block text-xs text-slate-500">{{ __('Notes') }}</span><textarea name="notes" rows="2" maxlength="5000" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Optional payroll or review note') }}">{{ old('notes') }}</textarea></label>
                    <button class="sm:col-span-2 rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">{{ __('Generate draft') }}</button>
                </form>
            </div>
        </section>
    @endif

    <div class="grid gap-5 2xl:grid-cols-[minmax(0,1.4fr)_minmax(420px,.8fr)]">
        <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4"><h2 class="font-semibold">{{ __('Commission rules') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('Scope a rule to all salesmen or one salesman, and optionally to a territory, product, target type, or currency.') }}</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">{{ __('Rule') }}</th><th class="px-4 py-3">{{ __('Basis') }}</th><th class="px-4 py-3">{{ __('Reward') }}</th><th class="px-4 py-3">{{ __('Scope') }}</th><th class="px-4 py-3">{{ __('Effective') }}</th><th class="px-4 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($rules as $rule)
                            <tr class="{{ $rule->is_active ? '' : 'opacity-55' }}">
                                <td class="px-4 py-4"><p class="font-semibold">{{ $rule->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $rule->code }} · {{ $rule->is_active ? __('Active') : __('Inactive') }}</p></td>
                                <td class="px-4 py-4"><span class="rounded-full bg-white/5 px-2 py-1 text-xs">{{ __(str($rule->basis_type)->replace('_', ' ')->title()->toString()) }}</span>@if($rule->minimum_basis !== null)<p class="mt-2 text-xs text-slate-500">{{ __('Minimum') }}: {{ number_format((float) $rule->minimum_basis, 2) }}</p>@endif</td>
                                <td class="px-4 py-4">@if($rule->reward_type === 'percentage')<span class="font-semibold text-emerald-300">{{ number_format((float) $rule->rate, 2) }}%</span>@else<span class="font-semibold text-emerald-300">{{ $rule->currency }} {{ number_format((float) $rule->rate, 2) }}</span>@endif<p class="mt-1 text-xs text-slate-500">{{ __(str($rule->reward_type)->title()->toString()) }}</p></td>
                                <td class="px-4 py-4 text-xs text-slate-400"><p>{{ $rule->salesman?->full_name ?? __('All salesmen') }}</p>@if($rule->territory)<p>{{ $rule->territory->name }}</p>@endif @if($rule->product)<p>{{ $rule->product->sku }} · {{ $rule->product->name }}</p>@endif @if($rule->target_type)<p>{{ __(str($rule->target_type)->replace('_', ' ')->title()->toString()) }}</p>@endif @if($rule->currency && $rule->reward_type === 'percentage')<p>{{ __('Source currency') }}: {{ $rule->currency }}</p>@endif</td>
                                <td class="px-4 py-4 text-xs text-slate-400">{{ $rule->effective_from?->toDateString() }}<br>→ {{ $rule->effective_to?->toDateString() ?? __('Open-ended') }}</td>
                                <td class="px-4 py-4 text-right">@if($canManage)<a href="{{ route('admin.commissions.rules.edit', $rule) }}" class="text-xs font-semibold text-indigo-300 hover:text-indigo-200">{{ __('Edit') }}</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">{{ __('No commission rules yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rules->hasPages())<div class="border-t border-white/10 p-4">{{ $rules->links() }}</div>@endif
        </section>

        <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4"><h2 class="font-semibold">{{ __('Commission runs') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('Open a run to review salesman lines and currency totals before approval.') }}</p></div>
            <div class="divide-y divide-white/10">
                @forelse($runs as $run)
                    <a href="{{ route('admin.commissions.runs.show', $run) }}" class="block px-5 py-4 hover:bg-white/5">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $run->period_start?->toDateString() }} → {{ $run->period_end?->toDateString() }}</p><p class="mt-1 text-xs text-slate-500">{{ $run->lines_count }} {{ __('commission lines') }} · {{ __('Generated by') }} {{ $run->generator?->name ?? '—' }}</p></div><span class="rounded-full px-2.5 py-1 text-xs {{ $run->state === 'approved' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300' }}">{{ __(str($run->state)->title()->toString()) }}</span></div>
                        @if($run->approved_at)<p class="mt-2 text-xs text-emerald-300">{{ __('Approved') }} {{ $run->approved_at->format('Y-m-d H:i') }} · {{ $run->approver?->name }}</p>@endif
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500">{{ __('No commission runs yet.') }}</div>
                @endforelse
            </div>
            @if($runs->hasPages())<div class="border-t border-white/10 p-4">{{ $runs->links() }}</div>@endif
        </section>
    </div>
</x-layouts.app>
