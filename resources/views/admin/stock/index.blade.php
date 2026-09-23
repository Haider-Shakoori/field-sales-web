<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Salesman stock') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('Issue stock, review sellable quantities and reconcile damaged stock.') }}</p>
        </div>
    </div>

    <div class="mb-5 grid gap-4 xl:grid-cols-[1fr_1.4fr]">
        <form method="GET" class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <label class="mb-2 block text-sm text-slate-300">{{ __('Salesman') }}</label>
            <div class="flex gap-2">
                <select name="salesman" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">{{ __('Select salesman') }}</option>
                    @foreach($salesmen as $salesman)
                        <option value="{{ $salesman->uuid }}" @selected($selectedSalesman?->id === $salesman->id)>
                            {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                        </option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-white/10 px-4 py-3 font-semibold">{{ __('View') }}</button>
            </div>
        </form>

        @if(auth()->user()->hasPermission('stock:manage'))
            <form method="POST" action="{{ route('admin.stock.issue') }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                @csrf
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ __('Issue stock') }}</h2>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Add sellable stock to a salesman vehicle/account.') }}</p>
                    </div>
                    <button type="button" id="add-stock-row" class="rounded-lg bg-white/10 px-3 py-2 text-sm">{{ __('Add product') }}</button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <select name="salesman_id" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                        <option value="">{{ __('Select salesman') }}</option>
                        @foreach($salesmen as $salesman)
                            <option value="{{ $salesman->uuid }}" @selected($selectedSalesman?->id === $salesman->id)>
                                {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                            </option>
                        @endforeach
                    </select>
                    <input type="datetime-local" name="issued_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                </div>

                <div id="stock-items" class="mt-4 space-y-2">
                    <div class="grid gap-2 md:grid-cols-[1fr_150px_auto]" data-stock-row>
                        <select name="items[0][product_id]" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required>
                            <option value="">{{ __('Product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->uuid }}">{{ $product->sku }} · {{ $product->name }} ({{ $product->unit }})</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" placeholder="{{ __('Quantity') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required>
                        <button type="button" data-remove-stock-row class="rounded-lg bg-rose-500/10 px-3 text-rose-300">{{ __('Remove') }}</button>
                    </div>
                </div>

                <textarea name="notes" rows="2" placeholder="{{ __('Notes') }}" class="mt-3 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></textarea>
                <div class="mt-3 text-right">
                    <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">{{ __('Post stock issue') }}</button>
                </div>
            </form>
        @endif
    </div>

    @if($selectedSalesman)
        <div class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4">
                <h2 class="font-semibold">{{ $selectedSalesman->employee_code }} · {{ $selectedSalesman->full_name }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">{{ __('Product') }}</th>
                        <th class="px-5 py-3">{{ __('Sellable') }}</th>
                        <th class="px-5 py-3">{{ __('Damaged') }}</th>
                        <th class="px-5 py-3">{{ __('Unit') }}</th>
                        <th class="px-5 py-3">{{ __('Updated') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                    @forelse($balances as $balance)
                        <tr>
                            <td class="px-5 py-4">{{ $balance->product?->sku }} · {{ $balance->product?->name }}</td>
                            <td class="px-5 py-4 font-semibold">{{ number_format((float) $balance->sellable_qty, 4) }}</td>
                            <td class="px-5 py-4 {{ (float) $balance->damaged_qty > 0 ? 'text-amber-300' : 'text-slate-400' }}">
                                {{ number_format((float) $balance->damaged_qty, 4) }}
                            </td>
                            <td class="px-5 py-4">{{ $balance->product?->unit }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $balance->updated_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('No stock has been issued to this salesman.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4"><h2 class="font-semibold">{{ __('Recent stock issues') }}</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5"><tr><th class="px-5 py-3">{{ __('Issue') }}</th><th class="px-5 py-3">{{ __('Salesman') }}</th><th class="px-5 py-3">{{ __('Items') }}</th><th class="px-5 py-3">{{ __('Issued at') }}</th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($recentIssues as $issue)
                    <tr>
                        <td class="px-5 py-4 font-mono text-xs">{{ $issue->issue_number }}</td>
                        <td class="px-5 py-4">{{ $issue->salesman?->full_name }}</td>
                        <td class="px-5 py-4">{{ $issue->items->count() }}</td>
                        <td class="px-5 py-4">{{ $issue->issued_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('No stock issues yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <template id="stock-row-template">
        <div class="grid gap-2 md:grid-cols-[1fr_150px_auto]" data-stock-row>
            <select name="items[__INDEX__][product_id]" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required>
                <option value="">{{ __('Product') }}</option>
                @foreach($products as $product)
                    <option value="{{ $product->uuid }}">{{ $product->sku }} · {{ $product->name }} ({{ $product->unit }})</option>
                @endforeach
            </select>
            <input type="number" step="0.0001" min="0.0001" name="items[__INDEX__][quantity]" placeholder="{{ __('Quantity') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required>
            <button type="button" data-remove-stock-row class="rounded-lg bg-rose-500/10 px-3 text-rose-300">{{ __('Remove') }}</button>
        </div>
    </template>

    <script>
        (() => {
            const list = document.getElementById('stock-items');
            const template = document.getElementById('stock-row-template');
            const add = document.getElementById('add-stock-row');

            if (!list || !template || !add) return;

            add.addEventListener('click', () => {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', Date.now().toString()).trim();
                list.appendChild(wrapper.firstElementChild);
            });

            list.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-stock-row]');
                if (!button) return;
                if (list.querySelectorAll('[data-stock-row]').length <= 1) return;
                button.closest('[data-stock-row]').remove();
            });
        })();
    </script>
</x-layouts.app>
