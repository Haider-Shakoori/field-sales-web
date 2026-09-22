<div data-question-row class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
    <div class="mb-3 flex items-center justify-between gap-3">
        <span class="text-sm font-semibold">{{ __('Question') }} <span data-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></span>
        <button type="button" data-remove-question class="rounded-lg bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-300">{{ __('Remove') }}</button>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs text-slate-400">{{ __('Question') }}</label>
            <input name="questions[{{ $index }}][label]" value="{{ $question['label'] ?? '' }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-400">{{ __('Type') }}</label>
            <select name="questions[{{ $index }}][type]" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                @foreach($questionTypes as $type)
                    <option value="{{ $type }}" @selected(($question['type'] ?? 'yes_no') === $type)>
                        {{ __(str($type)->replace('_', ' ')->title()->toString()) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2">
            <label class="mb-1 block text-xs text-slate-400">{{ __('Help text') }}</label>
            <input name="questions[{{ $index }}][help_text]" value="{{ $question['help_text'] ?? '' }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
        </div>
        <div class="lg:col-span-2">
            <label class="mb-1 block text-xs text-slate-400">{{ __('Choice options') }}</label>
            <textarea name="questions[{{ $index }}][options_text]" rows="3" placeholder="{{ __('One option per line') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">{{ $question['options_text'] ?? '' }}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-400">{{ __('Minimum number') }}</label>
            <input type="number" step="any" name="questions[{{ $index }}][min]" value="{{ $question['min'] ?? '' }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-400">{{ __('Maximum number') }}</label>
            <input type="number" step="any" name="questions[{{ $index }}][max]" value="{{ $question['max'] ?? '' }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="questions[{{ $index }}][is_required]" value="0">
            <input type="checkbox" name="questions[{{ $index }}][is_required]" value="1" @checked(filter_var($question['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN))>
            {{ __('Required answer') }}
        </label>
    </div>
</div>
