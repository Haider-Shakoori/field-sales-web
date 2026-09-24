<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Fraud & anomaly monitoring</h1>
        <p class="mt-1 text-sm text-slate-400">Deterministic risk signals from captured GPS, visits, collections and expenses. FieldPulse surfaces evidence for review; it does not accuse or assign a synthetic fraud score.</p>
    </div>

    <form method="GET" action="{{ route('admin.alerts.index') }}" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-5">
        <label class="text-sm">
            <span class="mb-1 block text-xs text-slate-400">From</span>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-xs text-slate-400">To</span>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-xs text-slate-400">Severity</span>
            <select name="severity" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
                <option value="">All</option>
                @foreach(['low', 'medium', 'high'] as $severity)
                    <option value="{{ $severity }}" @selected(($filters['severity'] ?? null) === $severity)>{{ str($severity)->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-xs text-slate-400">Review state</span>
            <select name="state" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
                <option value="">All</option>
                <option value="open" @selected(($filters['state'] ?? null) === 'open')>Open</option>
                <option value="reviewed" @selected(($filters['state'] ?? null) === 'reviewed')>Reviewed</option>
            </select>
        </label>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-sky-500 px-4 py-2 font-semibold text-slate-950">Apply</button>
        </div>
    </form>

    <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Open visit flags</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['open_visit_flags'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Reviewed visit flags</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['reviewed_visit_flags'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Mock GPS points</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['mock_location_points'] }}</div></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><div class="text-xs text-slate-500">Derived risk signals</div><div class="mt-2 text-2xl font-bold">{{ $alerts['summary']['derived_anomaly_signals'] }}</div></div>
    </section>

    <section class="mb-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">Suspicious visit evidence</h2>
        </div>
        <div class="divide-y divide-white/10">
            @forelse($alerts['flags'] as $flag)
                <div class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ str($flag->reason_code)->replace('_', ' ')->title() }}</span>
                            <span class="rounded-full bg-white/5 px-2 py-1 text-xs">{{ str($flag->severity)->title() }}</span>
                            @if($flag->reviewed_at)
                                <span class="rounded-full bg-emerald-500/10 px-2 py-1 text-xs text-emerald-300">Reviewed</span>
                            @else
                                <span class="rounded-full bg-amber-500/10 px-2 py-1 text-xs text-amber-300">Open</span>
                            @endif
                        </div>
                        <div class="mt-2 text-sm text-slate-400">
                            {{ $flag->visit?->salesman?->full_name ?? 'Unknown salesman' }} ·
                            {{ $flag->visit?->customer?->name ?? 'Unknown customer' }} ·
                            {{ $flag->created_at?->format('Y-m-d H:i') }}
                        </div>
                        @if($flag->details)
                            <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-400">{{ json_encode($flag->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    </div>
                    @if($canReview && ! $flag->reviewed_at)
                        <form method="POST" action="{{ route('admin.alerts.review', $flag) }}" class="w-full lg:w-72">
                            @csrf
                            @method('PATCH')
                            <textarea name="review_notes" rows="2" placeholder="Review note (optional)" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm"></textarea>
                            <button class="mt-2 w-full rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-slate-950">Mark reviewed</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="px-5 py-10 text-center text-slate-400">No suspicious visit evidence in this period.</div>
            @endforelse
        </div>
    </section>


    <section class="mb-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4"><h2 class="font-semibold">Deterministic anomaly signals</h2><p class="mt-1 text-xs text-slate-400">Outside-geofence collections, overpayments, and suspicious duplicate collection/expense windows. These are review cues, not proof of wrongdoing.</p></div>
        <div class="divide-y divide-white/10">@forelse($alerts['derived_signals'] as $signal)<div class="px-5 py-4"><div class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ str($signal['type'])->replace('_',' ')->title() }}</span><span class="rounded-full bg-white/5 px-2 py-1 text-xs">{{ str($signal['severity'])->title() }}</span></div><p class="mt-2 text-sm text-slate-400">{{ $signal['salesman'] ?? 'Unknown salesman' }}@if($signal['customer']) · {{ $signal['customer'] }}@endif · {{ $signal['occurred_at'] }}</p><p class="mt-2 text-sm text-slate-300">{{ $signal['detail'] }}</p></div>@empty<div class="px-5 py-10 text-center text-slate-400">No derived anomaly signals in this period.</div>@endforelse</div>
    </section>
    <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">Mock-location GPS evidence</h2>
            <p class="mt-1 text-xs text-slate-400">Raw points explicitly reported as mock locations by the mobile GPS payload.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Recorded</th><th class="px-5 py-3">Coordinates</th><th class="px-5 py-3">Accuracy</th><th class="px-5 py-3">Provider</th></tr></thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($alerts['mock_points'] as $point)
                        @php($salesman = $alerts['mock_salesmen']->get($point->salesman_id))
                        <tr>
                            <td class="px-5 py-4">{{ $salesman?->full_name ?? 'Unknown' }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $point->recorded_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $point->latitude }}, {{ $point->longitude }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $point->horizontal_accuracy }} m</td>
                            <td class="px-5 py-4 text-slate-400">{{ $point->provider ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No mock-location GPS evidence in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
