<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $customerReturn->return_number }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $customerReturn->customer?->name }} · {{ $customerReturn->salesman?->full_name }}</p>
        </div>
        <a href="{{ route('admin.returns.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">{{ __('Back') }}</a>
    </div>

    <div class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase text-slate-400">{{ __('Status') }}</p>
            <p class="mt-2 font-semibold">{{ __(str($customerReturn->status)->title()->toString()) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase text-slate-400">{{ __('Returned at') }}</p>
            <p class="mt-2 font-semibold">{{ $customerReturn->returned_at?->format('Y-m-d H:i:s') }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase text-slate-400">{{ __('Order') }}</p>
            <p class="mt-2 font-semibold">{{ $customerReturn->order?->order_number ?? __('Not linked') }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase text-slate-400">{{ __('Reason') }}</p>
            <p class="mt-2 font-semibold">{{ $customerReturn->reason }}</p>
        </div>
    </div>

    @if($customerReturn->notes)
        <div class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">{{ __('Notes') }}</h2>
            <p class="mt-2 whitespace-pre-wrap text-sm text-slate-300">{{ $customerReturn->notes }}</p>
        </div>
    @endif

    <section class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">{{ __('Returned items') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5">
                <tr>
                    <th class="px-5 py-3">{{ __('Product') }}</th>
                    <th class="px-5 py-3">{{ __('Quantity') }}</th>
                    <th class="px-5 py-3">{{ __('Condition') }}</th>
                    <th class="px-5 py-3">{{ __('Reason') }}</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @foreach($customerReturn->items as $item)
                    <tr>
                        <td class="px-5 py-4">{{ $item->product_sku }} · {{ $item->product_name }}</td>
                        <td class="px-5 py-4">{{ number_format((float) $item->quantity, 4) }} {{ $item->unit }}</td>
                        <td class="px-5 py-4">
                            <span class="{{ $item->condition === 'sellable' ? 'text-emerald-300' : 'text-rose-300' }}">
                                {{ __(str($item->condition)->title()->toString()) }}
                            </span>
                        </td>
                        <td class="px-5 py-4">{{ $item->reason ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if($customerReturn->status === 'pending' && auth()->user()->hasPermission('inventory:manage'))
        <form method="POST" action="{{ route('admin.returns.status', $customerReturn) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            @csrf
            @method('PATCH')
            <h2 class="font-semibold">{{ __('Review return') }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('Approved sellable items are added back to sellable van stock. Damaged items are added to damaged stock.') }}</p>

            <textarea name="status_note" rows="3" placeholder="{{ __('Review note / rejection reason') }}" class="mt-4 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></textarea>

            <div class="mt-4 flex flex-wrap gap-3">
                <button name="status" value="approved" class="rounded-xl bg-emerald-500/20 px-4 py-2.5 font-semibold text-emerald-300">{{ __('Approve return') }}</button>
                <button name="status" value="rejected" class="rounded-xl bg-rose-500/20 px-4 py-2.5 font-semibold text-rose-300">{{ __('Reject return') }}</button>
            </div>
        </form>
    @elseif($customerReturn->status_note)
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">{{ __('Review note') }}</h2>
            <p class="mt-2 whitespace-pre-wrap text-sm text-slate-300">{{ $customerReturn->status_note }}</p>
        </div>
    @endif
</x-layouts.app>
