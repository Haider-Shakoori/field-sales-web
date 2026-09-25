<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Operational alerts & anomalies</h1>
        <p class="mt-1 text-sm text-slate-400">Evidence-based signals from visits, GPS, collections, expenses and orders. FieldPulse does not label a person as fraudulent or generate an opaque fraud score.</p>
    </div>

    <form method="GET" action="{{ route('admin.alerts.index') }}" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 sm:grid-cols-2 xl:grid-cols-6">
        <label class="text-sm"><span class="mb-1 block text-xs text-slate-400">From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2"></label>
        <label class="text-sm"><span class="mb-1 block text-xs text-slate-400">To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2"></label>
        <label class="text-sm"><span class="mb-1 block text-xs text-slate-400">Type</span><select name="type" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2"><option value="">All</option>@foreach(['visit', 'gps', 'collection', 'expense', 'order'] as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ str($type)->title() }}</option>@endforeach</select></label>
        <label class="text-sm"><span class="mb-1 block text-xs text-slate-400">Severity</span><select name="severity" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2"><option value="">All</option>@foreach(['low', 'medium', 'high'] as $severity)<option value="{{ $severity }}" @selected(($filters['severity'] ?? null) === $severity)>{{ str($severity)->title() }}</option>@endforeach</select></label>
        <label class="text-sm"><span class="mb-1 block text-xs text-slate-400">Review state</span><select name="state" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2"><option value="">All</option><option value="open" @selected(($filters['state'] ?? null) === 'open')>Open</option><option value="reviewed" @selected(($filters['state'] ?? null) === 'reviewed')>Reviewed</option></select></label>
        <div class="flex items-end"><button class="w-full rounded-lg bg-sky-500 px-4 py-2 font-semibold text-slate-950">Apply</button></div>
    </form>

    <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Open anomalies</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['open_anomalies'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">High severity</div><div class="mt-2 text-2xl font-bold text-rose-300">{{ $alerts['summary']['high_anomalies'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Reviewed anomalies</div><div class="mt-2 text-2xl font-bold text-emerald-300">{{ $alerts['summary']['reviewed_anomalies'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Open visit flags</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['open_visit_flags'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Reviewed visit flags</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['reviewed_visit_flags'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Mock GPS points</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['mock_location_points'] }}</div></div>
    </section>

    <section class="mb-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">Order, expense & collection anomalies</h2>
            <p class="mt-1 text-xs text-slate-400">Collection signals use captured geofence/balance evidence. Order and expense outliers require at least five comparable records and trigger only at 3× or more of the same-currency median.</p>
        </div>
        <div class="divide-y divide-white/10">
            @forelse($alerts['anomalies'] as $anomaly)
                <article class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $anomaly->title }}</span>
                            <span class="rounded-full bg-white/5 px-2 py-1 text-[10px] uppercase text-slate-400">{{ $anomaly->entity_type }}</span>
                            <span class="rounded-full px-2 py-1 text-[10px] uppercase {{ $anomaly->severity === 'high' ? 'bg-rose-500/10 text-rose-300' : ($anomaly->severity === 'medium' ? 'bg-amber-500/10 text-amber-300' : 'bg-slate-800 text-slate-400') }}">{{ $anomaly->severity }}</span>
                            <span class="rounded-full px-2 py-1 text-[10px] uppercase {{ $anomaly->state === 'reviewed' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-sky-500/10 text-sky-300' }}">{{ $anomaly->state }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-300">{{ $anomaly->summary }}</p>
                        <div class="mt-2 text-xs text-slate-500">{{ $anomaly->salesman?->full_name ?? 'Unknown salesman' }} · {{ str($anomaly->rule_code)->replace('_', ' ')->title() }} · {{ $anomaly->occurred_at?->format('Y-m-d H:i') }}</div>
                        @if($anomaly->evidence)<div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">@foreach($anomaly->evidence as $label => $value)<div class="rounded-lg bg-slate-950 px-3 py-2 text-xs"><span class="text-slate-500">{{ str($label)->replace('_', ' ')->title() }}:</span> <span class="text-slate-300">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === null ? '—' : $value) }}</span></div>@endforeach</div>@endif
                        @if($anomaly->reviewed_at)<div class="mt-3 rounded-lg bg-emerald-500/5 px-3 py-2 text-xs text-emerald-200">Reviewed {{ $anomaly->reviewed_at->format('Y-m-d H:i') }} by {{ $anomaly->reviewer?->name ?? 'User' }}@if($anomaly->review_notes) · {{ $anomaly->review_notes }}@endif</div>@endif
                    </div>
                    @if($canReview && $anomaly->state === 'open')
                        <form method="POST" action="{{ route('admin.alerts.anomalies.review', $anomaly) }}" class="self-start">@csrf @method('PATCH')<textarea name="review_notes" rows="3" maxlength="5000" placeholder="Review note (optional)" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm"></textarea><button class="mt-2 w-full rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-slate-950">Mark reviewed</button></form>
                    @endif
                </article>
            @empty
                <div class="px-5 py-10 text-center text-slate-400">No order, expense or collection anomaly evidence matched the selected filters.</div>
            @endforelse
        </div>
    </section>

    <section class="mb-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4"><h2 class="font-semibold">Suspicious visit evidence</h2></div>
        <div class="divide-y divide-white/10">
            @forelse($alerts['flags'] as $flag)
                <div class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto]"><div><div class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ str($flag->reason_code)->replace('_', ' ')->title() }}</span><span class="rounded-full bg-white/5 px-2 py-1 text-xs">{{ str($flag->severity)->title() }}</span>@if($flag->reviewed_at)<span class="rounded-full bg-emerald-500/10 px-2 py-1 text-xs text-emerald-300">Reviewed</span>@else<span class="rounded-full bg-amber-500/10 px-2 py-1 text-xs text-amber-300">Open</span>@endif</div><div class="mt-2 text-sm text-slate-400">{{ $flag->visit?->salesman?->full_name ?? 'Unknown salesman' }} · {{ $flag->visit?->customer?->name ?? 'Unknown customer' }} · {{ $flag->created_at?->format('Y-m-d H:i') }}</div>@if($flag->details)<pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-400">{{ json_encode($flag->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@endif</div>@if($canReview && ! $flag->reviewed_at)<form method="POST" action="{{ route('admin.alerts.review', $flag) }}" class="w-full lg:w-72">@csrf @method('PATCH')<textarea name="review_notes" rows="2" placeholder="Review note (optional)" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm"></textarea><button class="mt-2 w-full rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-slate-950">Mark reviewed</button></form>@endif</div>
            @empty<div class="px-5 py-10 text-center text-slate-400">No suspicious visit evidence in this period.</div>@endforelse
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4"><h2 class="font-semibold">Mock-location GPS evidence</h2><p class="mt-1 text-xs text-slate-400">Raw points explicitly reported as mock locations by the mobile GPS payload.</p></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Recorded</th><th class="px-5 py-3">Coordinates</th><th class="px-5 py-3">Accuracy</th><th class="px-5 py-3">Provider</th></tr></thead><tbody class="divide-y divide-white/10">@forelse($alerts['mock_points'] as $point)@php($salesman = $alerts['mock_salesmen']->get($point->salesman_id))<tr><td class="px-5 py-4">{{ $salesman?->full_name ?? 'Unknown' }}</td><td class="px-5 py-4 text-slate-400">{{ $point->recorded_at?->format('Y-m-d H:i:s') }}</td><td class="px-5 py-4 text-slate-400">{{ $point->latitude }}, {{ $point->longitude }}</td><td class="px-5 py-4 text-slate-400">{{ $point->horizontal_accuracy }} m</td><td class="px-5 py-4 text-slate-400">{{ $point->provider ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No mock-location GPS evidence in this period.</td></tr>@endforelse</tbody></table></div>
    </section>
</x-layouts.app>
