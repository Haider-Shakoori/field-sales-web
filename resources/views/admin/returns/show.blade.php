<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $return->return_number }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $return->customer?->name }} · {{ $return->salesman?->full_name }}</p>
        </div>
        <a href="{{ route('admin.returns.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">{{ __('Back') }}</a>
    </div>

    <div class="mb-5 grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs text-slate-400">{{ __('Status') }}</p><p class="mt-2 text-lg font-semibold">{{ __(str($return->status)->title()->toString()) }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs text-slate-400">{{ __('Returned at') }}</p><p class="mt-2 text-lg font-semibold">{{ $return->returned_at?->format('Y-m-d H:i') }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs text-slate-400">{{ __('Order') }}</p><p class="mt-2 text-lg font-semibold">{{ $return->order?->order_number ?? '—' }}</p></div>
    </div>

    <div class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-white/5"><tr><th class="px-5 py-3">{{ __('Product') }}</th><th class="px-5 py-3">{{ __('Quantity') }}</th><th class="px-5 py-3">{{ __('Condition') }}</th><th class="px-5 py-3">{{ __('Reason') }}</th></tr></thead>
            <tbody class="divide-y divide-white/10">
            @foreach($return->items as $item)
                <tr>
                    <td class="px-5 py-4">{{ $item->product?->sku }} · {{ $item->product?->name }}</td>
                    <td class="px-5 py-4">{{ number_format((float) $item->quantity, 4) }} {{ $item->product?->unit }}</td>
                    <td class="px-5 py-4">{{ __(str($item->condition)->title()->toString()) }}</td>
                    <td class="px-5 py-4">{{ $item->reason ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if($return->notes)
        <div class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">{{ __('Notes') }}</h2><p class="mt-2 text-sm text-slate-300">{{ $return->notes }}</p></div>
    @endif

    @if($return->status === 'pending' && auth()->user()->hasPermission('returns:manage'))
        <form method="POST" action="{{ route('admin.returns.status', $return) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            @csrf
            @method('PATCH')
            <h2 class="font-semibold">{{ __('Review return') }}</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-[180px_1fr_auto]">
                <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                    <option value="approved">{{ __('Approve') }}</option>
                    <option value="rejected">{{ __('Reject') }}</option>
                </select>
                <input name="status_note" placeholder="{{ __('Reason / review note') }}" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">{{ __('Submit review') }}</button>
            </div>
        </form>
    @elseif($return->status_note)
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">{{ __('Review note') }}</h2><p class="mt-2 text-sm text-slate-300">{{ $return->status_note }}</p></div>
    @endif
</x-layouts.app>
