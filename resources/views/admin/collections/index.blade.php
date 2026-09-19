<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Collections</h1>
        <p class="mt-1 text-sm text-slate-400">Field collections, receipts, payment methods and verification status.</p>
    </div>

    <form class="mb-5 flex flex-wrap gap-3">
        <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All statuses</option>
            @foreach(\App\Models\Collection::STATUSES as $value)
                <option value="{{ $value }}" @selected($status === $value)>{{ str($value)->title() }}</option>
            @endforeach
        </select>
        <select name="payment_method" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All payment methods</option>
            @foreach(\App\Models\Collection::PAYMENT_METHODS as $value)
                <option value="{{ $value }}" @selected($paymentMethod === $value)>{{ str($value)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5"><tr><th class="px-5 py-3">Receipt</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Collected</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Method</th><th class="px-5 py-3">Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($collections as $collection)
                    <tr>
                        <td class="px-5 py-4">{{ $collection->receipt_number }}</td>
                        <td class="px-5 py-4">{{ $collection->customer?->name ?? 'Deleted customer' }}</td>
                        <td class="px-5 py-4">{{ $collection->salesman?->full_name ?? $collection->salesman?->user?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $collection->collected_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-4">{{ number_format((float) $collection->amount, 2) }} {{ $collection->currency }}</td>
                        <td class="px-5 py-4">{{ str($collection->payment_method)->replace('_', ' ')->title() }}</td>
                        <td class="px-5 py-4">
                            {{ str($collection->status)->title() }}
                            @if($collection->overpayment_flag)
                                <span class="ml-1 rounded bg-amber-500/20 px-2 py-1 text-xs text-amber-300">review</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.collections.show', $collection) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">No collections found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $collections->links() }}</div>
</x-layouts.app>
