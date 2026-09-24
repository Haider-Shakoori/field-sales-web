<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $collection->receipt_number }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $collection->customer?->name }} · {{ __(str($collection->status)->title()->toString()) }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.collections.receipt', $collection) }}" target="_blank" class="rounded-xl bg-white/10 px-4 py-2.5">{{ __('Receipt') }}</a>
            <a href="{{ route('admin.collections.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">{{ __('Back') }}</a>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
            <h2 class="font-semibold">{{ __('Collection detail') }}</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-400">{{ __('Amount') }}</dt><dd class="text-lg font-semibold">{{ number_format((float) $collection->amount, 2) }} {{ $collection->currency }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Payment method') }}</dt><dd>{{ __(str($collection->payment_method)->replace('_', ' ')->title()->toString()) }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Collected') }}</dt><dd>{{ $collection->collected_at?->format('Y-m-d H:i:s') }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Reference') }}</dt><dd>{{ $collection->reference_number ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Salesman') }}</dt><dd>{{ $collection->salesman?->full_name ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Visit') }}</dt><dd>{{ $collection->visit?->uuid ?? __('Not linked') }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('GPS') }}</dt><dd>{{ $collection->latitude }}, {{ $collection->longitude }} (±{{ $collection->accuracy }} m)</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Customer distance') }}</dt><dd>{{ $collection->distance_meters === null ? __('Unknown') : round((float) $collection->distance_meters, 1).' m' }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Geofence') }}</dt><dd>{{ $collection->within_geofence === null ? __('Unknown') : ($collection->within_geofence ? __('Inside') : __('Outside')) }}</dd></div>
                <div><dt class="text-sm text-slate-400">{{ __('Balance at capture') }}</dt><dd>{{ number_format((float) $collection->balance_before, 2) }} {{ $collection->currency }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-slate-400">{{ __('Notes') }}</dt><dd>{{ $collection->notes ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-slate-400">{{ __('Status note') }}</dt><dd>{{ $collection->status_note ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">{{ __('Customer balance') }}</h2>
            <div class="mt-4 space-y-3">
                @forelse($balances as $balance)
                    <div class="rounded-xl bg-slate-950 p-3">
                        <div class="font-semibold">{{ $balance['currency'] }}</div>
                        <div class="mt-2 text-sm text-slate-400">{{ __('Receivable') }}: {{ number_format($balance['receivable_total'], 2) }}</div>
                        <div class="text-sm text-slate-400">{{ __('Verified') }}: {{ number_format($balance['verified_collections'], 2) }}</div>
                        <div class="text-sm text-slate-400">{{ __('Pending') }}: {{ number_format($balance['pending_collections'], 2) }}</div>
                        <div class="mt-1 font-semibold">{{ __('Outstanding') }}: {{ number_format($balance['outstanding_balance'], 2) }}</div>
                        <div class="text-sm text-slate-400">{{ __('Available to collect') }}: {{ number_format($balance['available_to_collect'], 2) }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('No receivable balance.') }}</p>
                @endforelse
            </div>
        </section>

        @if(auth()->user()->hasPermission('collections:manage'))
            <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-3">
                <h2 class="font-semibold">{{ __('Verification') }}</h2>
                @if($collection->status === 'pending')
                    <form method="POST" action="{{ route('admin.collections.status', $collection) }}" class="mt-4 grid gap-3 md:grid-cols-[220px_1fr_auto]">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                            <option value="verified">{{ __('Verify') }}</option>
                            <option value="rejected">{{ __('Reject') }}</option>
                            <option value="cancelled">{{ __('Cancel') }}</option>
                        </select>
                        <input name="status_note" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" placeholder="{{ __('Reason required for reject/cancel') }}">
                        <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold">{{ __('Update status') }}</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-400">{{ __('This collection is terminal. Financial corrections should use an explicit reversal workflow rather than editing verified history.') }}</p>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
