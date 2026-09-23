<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ __('Van stock') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Manage salesman vehicle stock, reservations, damaged goods and movement history.') }}</p>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl border border-white/10 bg-slate-900 p-5">
        <select name="salesman" class="min-w-72 rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            <option value="">{{ __('Select salesman') }}</option>
            @foreach($salesmen as $salesman)
                <option value="{{ $salesman->uuid }}" @selected($selectedSalesman?->id === $salesman->id)>
                    {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                </option>
            @endforeach
        </select>
        <button class="rounded-xl bg-white/10 px-4 py-3 font-semibold">{{ __('Open stock') }}</button>
    </form>

    @if($selectedSalesman)
        @php
            $sellable = (float) $balances->sum('sellable_quantity');
            $reserved = (float) $balances->sum('reserved_quantity');
            $available = (float) $balances->sum(fn ($balance) => $balance->available_quantity);
            $damaged = (float) $balances->sum('damaged_quantity');
        @endphp

        <div class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ $selectedSalesman->employee_code }} · {{ $selectedSalesman->full_name }}</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ __('Van stock control') }}:
                        <span class="{{ $selectedSalesman->van_stock_enabled ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $selectedSalesman->van_stock_enabled ? __('Enabled') : __('Disabled') }}
                        </span>
                    </p>
                </div>

                @if(auth()->user()->hasPermission('inventory:manage'))
                    <form method="POST" action="{{ route('admin.van-stock.control', $selectedSalesman) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="enabled" value="{{ $selectedSalesman->van_stock_enabled ? 0 : 1 }}">
                        <button class="rounded-xl {{ $selectedSalesman->van_stock_enabled ? 'bg-rose-500/15 text-rose-300' : 'bg-emerald-500/15 text-emerald-300' }} px-4 py-2.5 font-semibold">
                            {{ $selectedSalesman->van_stock_enabled ? __('Disable stock control') : __('Enable stock control') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                [__('Sellable'), $sellable],
                [__('Reserved'), $reserved],
                [__('Available'), $available],
                [__('Damaged'), $damaged],
            ] as [$label, $value])
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format($value, 4) }}</p>
                </div>
            @endforeach
        </div>

        @if(auth()->user()->hasPermission('inventory:manage'))
            <form method="POST" action="{{ route('admin.van-stock.adjust') }}" class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
                @csrf
                <input type="hidden" name="salesman_id" value="{{ $selectedSalesman->id }}">
                <h2 class="mb-4 font-semibold">{{ __('Load / adjust stock') }}</h2>

                <div class="grid gap-3 lg:grid-cols-5">
                    <select name="product_id" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                        <option value="">{{ __('Product') }}</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->sku }} · {{ $product->name }}</option>
                        @endforeach
                    </select>

                    <select name="bucket" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                        <option value="sellable">{{ __('Sellable') }}</option>
                        <option value="damaged">{{ __('Damaged') }}</option>
                    </select>

                    <input type="number" step="0.0001" name="quantity" placeholder="{{ __('Quantity (+/-)') }}" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>

                    <input name="note" placeholder="{{ __('Reason / reference') }}" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>

                    <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">{{ __('Apply adjustment') }}</button>
                </div>

                <p class="mt-3 text-xs text-slate-400">{{ __('Use a positive quantity to load stock and a negative quantity to remove or correct stock.') }}</p>
            </form>
        @endif

        <section class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4">
                <h2 class="font-semibold">{{ __('Current balances') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">{{ __('Product') }}</th>
                        <th class="px-5 py-3">{{ __('Sellable') }}</th>
                        <th class="px-5 py-3">{{ __('Reserved') }}</th>
                        <th class="px-5 py-3">{{ __('Available') }}</th>
                        <th class="px-5 py-3">{{ __('Damaged') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                    @forelse($balances as $balance)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-medium">{{ $balance->product?->sku }} · {{ $balance->product?->name }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ $balance->product?->unit }}</div>
                            </td>
                            <td class="px-5 py-4">{{ number_format((float) $balance->sellable_quantity, 4) }}</td>
                            <td class="px-5 py-4">{{ number_format((float) $balance->reserved_quantity, 4) }}</td>
                            <td class="px-5 py-4 font-semibold">{{ number_format($balance->available_quantity, 4) }}</td>
                            <td class="px-5 py-4">{{ number_format((float) $balance->damaged_quantity, 4) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('No van stock has been loaded for this salesman yet.') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4">
                <h2 class="font-semibold">{{ __('Recent stock movements') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">{{ __('Time') }}</th>
                        <th class="px-5 py-3">{{ __('Product') }}</th>
                        <th class="px-5 py-3">{{ __('Movement') }}</th>
                        <th class="px-5 py-3">{{ __('Sellable Δ') }}</th>
                        <th class="px-5 py-3">{{ __('Reserved Δ') }}</th>
                        <th class="px-5 py-3">{{ __('Damaged Δ') }}</th>
                        <th class="px-5 py-3">{{ __('Reference') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                    @forelse($movements as $movement)
                        <tr>
                            <td class="px-5 py-4 whitespace-nowrap">{{ $movement->occurred_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-4">{{ $movement->product?->sku }} · {{ $movement->product?->name }}</td>
                            <td class="px-5 py-4">{{ str($movement->movement_type)->replace('_', ' ')->title() }}</td>
                            <td class="px-5 py-4">{{ number_format((float) $movement->sellable_delta, 4) }}</td>
                            <td class="px-5 py-4">{{ number_format((float) $movement->reserved_delta, 4) }}</td>
                            <td class="px-5 py-4">{{ number_format((float) $movement->damaged_delta, 4) }}</td>
                            <td class="px-5 py-4">
                                @if($movement->order)
                                    {{ $movement->order->order_number }}
                                @elseif($movement->customerReturn)
                                    {{ $movement->customerReturn->return_number }}
                                @else
                                    {{ $movement->note ?: '—' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('No stock movements recorded yet.') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-10 text-center text-slate-400">
            {{ __('Select a salesman to view and manage van stock.') }}
        </div>
    @endif
</x-layouts.app>
