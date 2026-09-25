<x-layouts.app>
    @php
        $selectedBasis = old('basis_type', $rule->basis_type ?: 'sales_amount');
        $selectedReward = old('reward_type', $rule->reward_type ?: 'percentage');
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.commissions.index') }}" class="text-xs font-semibold text-indigo-300 hover:text-indigo-200">← {{ __('Commissions') }}</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ $rule->exists ? __('Edit commission rule') : __('New commission rule') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Rules are deterministic and can be scoped to a salesman, territory, product, target type, or currency.') }}</p>
    </div>

    <form method="POST" action="{{ $action }}" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Code') }}</span><input required name="code" maxlength="80" value="{{ old('code', $rule->code) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 uppercase" placeholder="SALES-2PCT"></label>
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Name') }}</span><input required name="name" maxlength="180" value="{{ old('name', $rule->name) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Standard sales commission') }}"></label>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Basis') }}</span><select id="basis_type" required name="basis_type" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">@foreach($basisTypes as $basis)<option value="{{ $basis }}" @selected($selectedBasis === $basis)>{{ __(str($basis)->replace('_', ' ')->title()->toString()) }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Reward type') }}</span><select id="reward_type" required name="reward_type" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">@foreach($rewardTypes as $reward)<option value="{{ $reward }}" @selected($selectedReward === $reward)>{{ __(str($reward)->title()->toString()) }}</option>@endforeach</select></label>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <label><span id="rate-label" class="mb-1 block text-sm text-slate-300">{{ __('Rate') }}</span><input required type="number" min="0.0001" step="0.0001" name="rate" value="{{ old('rate', $rule->rate) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></label>
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Currency') }}</span><input name="currency" maxlength="3" value="{{ old('currency', $rule->currency) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 uppercase" placeholder="{{ __('Optional for percentage rules') }}"></label>
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Minimum basis') }}</span><input type="number" min="0" step="0.0001" name="minimum_basis" value="{{ old('minimum_basis', $rule->minimum_basis) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="0"></label>
            </div>

            <div class="rounded-xl border border-white/10 bg-slate-950/60 p-4">
                <h2 class="text-sm font-semibold">{{ __('Rule scope') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Leave salesman and territory empty to apply the rule broadly. Product is required only for product sales rules.') }}</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label><span class="mb-1 block text-sm text-slate-300">{{ __('Salesman') }}</span><select name="salesman_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('All salesmen') }}</option>@foreach($salesmen as $salesman)<option value="{{ $salesman->id }}" @selected((string) old('salesman_id', $rule->salesman_id) === (string) $salesman->id)>{{ $salesman->employee_code }} · {{ $salesman->full_name }}</option>@endforeach</select></label>
                    <label id="territory-field"><span class="mb-1 block text-sm text-slate-300">{{ __('Territory') }}</span><select name="territory_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('All territories') }}</option>@foreach($territories as $territory)<option value="{{ $territory->id }}" @selected((string) old('territory_id', $rule->territory_id) === (string) $territory->id)>{{ $territory->code }} · {{ $territory->name }}</option>@endforeach</select></label>
                    <label id="product-field"><span class="mb-1 block text-sm text-slate-300">{{ __('Product') }}</span><select name="product_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('Choose product') }}</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) old('product_id', $rule->product_id) === (string) $product->id)>{{ $product->sku }} · {{ $product->name }}</option>@endforeach</select></label>
                    <label id="target-field"><span class="mb-1 block text-sm text-slate-300">{{ __('Target type') }}</span><select name="target_type" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('Any achieved target') }}</option>@foreach($targetTypes as $targetType)<option value="{{ $targetType }}" @selected(old('target_type', $rule->target_type) === $targetType)>{{ __(str($targetType)->replace('_', ' ')->title()->toString()) }}</option>@endforeach</select></label>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Effective from') }}</span><input required type="date" name="effective_from" value="{{ old('effective_from', $rule->effective_from?->toDateString() ?? now()->toDateString()) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></label>
                <label><span class="mb-1 block text-sm text-slate-300">{{ __('Effective to') }}</span><input type="date" name="effective_to" value="{{ old('effective_to', $rule->effective_to?->toDateString()) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></label>
            </div>

            <label class="block"><span class="mb-1 block text-sm text-slate-300">{{ __('Notes') }}</span><textarea name="notes" rows="4" maxlength="5000" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">{{ old('notes', $rule->notes) }}</textarea></label>
            <div class="flex items-center gap-3"><input type="hidden" name="is_active" value="0"><input id="is_active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $rule->exists ? $rule->is_active : true)) class="h-4 w-4 rounded border-white/20 bg-slate-950"><label for="is_active" class="text-sm text-slate-300">{{ __('Rule is active') }}</label></div>

            <div class="flex flex-wrap gap-3"><button class="rounded-xl bg-indigo-500 px-5 py-2.5 font-semibold hover:bg-indigo-400">{{ $rule->exists ? __('Save rule') : __('Create rule') }}</button><a href="{{ route('admin.commissions.index') }}" class="rounded-xl border border-white/10 px-5 py-2.5 text-slate-300 hover:bg-white/5">{{ __('Cancel') }}</a></div>
        </section>

        <aside class="space-y-4 self-start xl:sticky xl:top-6">
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">{{ __('How calculations work') }}</h2><ul class="mt-3 space-y-2 text-xs leading-5 text-slate-400"><li>{{ __('Sales rules use approved orders only.') }}</li><li>{{ __('Collection rules use verified collections only.') }}</li><li>{{ __('Product rules use approved order line totals for the selected product.') }}</li><li>{{ __('Target rules pay a fixed reward for each target reaching at least 100% in the run period.') }}</li><li>{{ __('Percentage rules never convert currencies. Each source currency produces a separate line.') }}</li><li>{{ __('Matching rules stack; use scopes and effective dates to control intentional overlap.') }}</li></ul></div>
            @if($rule->exists)
                <div class="rounded-2xl border border-rose-400/20 bg-rose-500/5 p-5"><h2 class="font-semibold text-rose-200">{{ __('Delete rule') }}</h2><p class="mt-2 text-xs leading-5 text-rose-100/70">{{ __('Rules already referenced by a commission run cannot be deleted. Deactivate them instead to preserve audit history.') }}</p><form method="POST" action="{{ route('admin.commissions.rules.destroy', $rule) }}" class="mt-4">@csrf @method('DELETE')<button class="w-full rounded-xl bg-rose-500/15 px-4 py-2.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/25">{{ __('Delete rule') }}</button></form></div>
            @endif
        </aside>
    </form>

    <script>
        (() => {
            const basis = document.getElementById('basis_type');
            const reward = document.getElementById('reward_type');
            const product = document.getElementById('product-field');
            const target = document.getElementById('target-field');
            const territory = document.getElementById('territory-field');
            const rateLabel = document.getElementById('rate-label');
            const sync = () => {
                const targetRule = basis.value === 'target_achievement';
                product.hidden = basis.value !== 'product_sales_amount';
                target.hidden = !targetRule;
                territory.hidden = targetRule;
                if (targetRule) reward.value = 'fixed';
                reward.disabled = targetRule;
                rateLabel.textContent = targetRule || reward.value === 'fixed' ? @json(__('Fixed reward')) : @json(__('Percentage rate'));
            };
            basis.addEventListener('change', sync);
            reward.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-layouts.app>
