<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ __('Customer returns') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Review returned products before they are added back to sellable or damaged van stock.') }}</p>
    </div>

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-5 md:grid-cols-[220px_280px_auto]">
        <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">{{ __('All statuses') }}</option>
            @foreach(AppModelsCustomerReturn::STATUSES as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ __(str($option)->title()->toString()) }}</option>
            @endforeach
        </select>

        <select name="salesman" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">{{ __('All salesmen') }}</option>
            @foreach($salesmen as $salesman)
                <option value="{{ $salesman->uuid }}" @selected($selectedSalesman?->id === $salesman->id)>
                    {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                </option>
            @endforeach
        </select>

        <button class="rounded-xl bg-white/10 px-4 py-3 font-semibold">{{ __('Filter') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5">
                <tr>
                    <th class="px-5 py-3">{{ __('Return') }}</th>
                    <th class="px-5 py-3">{{ __('Customer') }}</th>
                    <th class="px-5 py-3">{{ __('Salesman') }}</th>
                    <th class="px-5 py-3">{{ __('Order') }}</th>
                    <th class="px-5 py-3">{{ __('Items') }}</th>
                    <th class="px-5 py-3">{{ __('Reason') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($returns as $return)
                    <tr>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $return->return_number }}</div>
                            <div class="mt-1 text-xs text-slate-400">{{ $return->returned_at?->format('Y-m-d H:i') }}</div>
                        </td>
                        <td class="px-5 py-4">{{ $return->customer?->name }}</td>
                        <td class="px-5 py-4">{{ $return->salesman?->employee_code }} · {{ $return->salesman?->full_name }}</td>
                        <td class="px-5 py-4">{{ $return->order?->order_number ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $return->items_count }}</td>
                        <td class="px-5 py-4">{{ $return->reason }}</td>
                        <td class="px-5 py-4">
                            <span class="{{ $return->status === 'approved' ? 'text-emerald-300' : ($return->status === 'rejected' ? 'text-rose-300' : 'text-amber-300') }}">
                                {{ __(str($return->status)->title()->toString()) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.returns.show', $return) }}" class="rounded-lg bg-white/10 px-3 py-2">{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-10 text-center text-slate-400">{{ __('No customer returns found.') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($returns->hasPages())
            <div class="border-t border-white/10 px-5 py-4">{{ $returns->links() }}</div>
        @endif
    </div>
</x-layouts.app>
