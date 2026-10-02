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
                    @foreach(\App\Models\SalesTarget::TYPES as $value)
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

        <div class="rounded-2xl border border-indigo-400/20 bg-indigo-500/10 p-5">
            <h2 class="font-semibold text-indigo-100">Gamification milestone rewards</h2>
            <p class="mt-1 text-xs text-indigo-100/70">Set points and cash bonus for each milestone. Leave both values at 0 to disable that milestone. Rewards are cumulative and each milestone is awarded only once.</p>
            @php($rewards = old('gamification_rewards', $target->gamification_rewards ?? []))
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach([80, 100, 120] as $milestone)
                    <label><span class="mb-1 block text-sm font-semibold text-slate-200">{{ $milestone }}% milestone</span><span class="mb-1 mt-3 block text-xs text-slate-400">Points reward</span>
                        <input name="reward_{{ $milestone }}" type="number" min="0" step="1" value="{{ old('reward_'.$milestone, $rewards[(string)$milestone] ?? 0) }}" class="fp-input w-full">
                        <span class="mb-1 mt-3 block text-xs text-slate-400">Cash bonus (AFN)</span>
                        <input name="bonus_{{ $milestone }}" type="number" min="0" step="0.01" value="{{ old('bonus_'.$milestone, $rewards['bonus_'.$milestone] ?? 0) }}" class="fp-input w-full">
                    </label>
                @endforeach
            </div>
            <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/40 p-3 text-xs leading-5 text-slate-300"><strong>Example:</strong> On a 100,000 AFN target, 80% is reached at 80,000 AFN. If rewards are configured at 80%, 100% and 120%, reaching 120% earns all three. Cash bonuses follow Earned → Approved → Paid. Achievements such as Target Achiever and Target Crusher are separate.</div>
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
