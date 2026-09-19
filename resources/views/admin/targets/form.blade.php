<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ $target->exists ? 'Edit future target' : 'New sales target' }}</h1>
        <p class="mt-1 text-sm text-slate-400">Amount targets require a currency. Count targets must use whole numbers.</p>
    </div>

    <form method="POST" action="{{ $action }}" class="max-w-3xl space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif

        <div>
            <label class="mb-2 block text-sm text-slate-300">Salesman</label>
            <select name="salesman_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                <option value="">Select salesman</option>
                @foreach($salesmen as $salesman)
                    <option value="{{ $salesman->uuid }}" @selected(old('salesman_id', $target->salesman?->uuid) === $salesman->uuid)>{{ $salesman->full_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm text-slate-300">Target type</label>
                <select name="target_type" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                    @foreach(AppModelsSalesTarget::TYPES as $value)
                        <option value="{{ $value }}" @selected(old('target_type', $target->target_type) === $value)>{{ str($value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm text-slate-300">Currency</label>
                <input name="currency" maxlength="3" value="{{ old('currency', $target->currency ?? 'AFN') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase" placeholder="AFN">
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm text-slate-300">Target value</label>
            <input name="target_value" type="number" step="0.0001" min="0.0001" value="{{ old('target_value', $target->target_value) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm text-slate-300">Period start</label>
                <input name="period_start" type="date" value="{{ old('period_start', $target->period_start?->toDateString()) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            </div>
            <div>
                <label class="mb-2 block text-sm text-slate-300">Period end</label>
                <input name="period_end" type="date" value="{{ old('period_end', $target->period_end?->toDateString()) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm text-slate-300">Notes</label>
            <textarea name="notes" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('notes', $target->notes) }}</textarea>
        </div>

        <div class="flex gap-3">
            <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold">Save target</button>
            <a href="{{ route('admin.targets.index') }}" class="rounded-xl bg-white/10 px-5 py-3">Cancel</a>
        </div>
    </form>
</x-layouts.app>
