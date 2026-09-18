<div class="grid gap-5 md:grid-cols-2">
    <label class="block"><span class="text-sm text-slate-300">Code</span><input name="code" value="{{ old('code', $priceList->code ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required></label>
    <label class="block"><span class="text-sm text-slate-300">Name</span><input name="name" value="{{ old('name', $priceList->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required></label>
    <label class="block"><span class="text-sm text-slate-300">Currency</span><input name="currency" maxlength="3" value="{{ old('currency', $priceList->currency ?? 'AFN') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase" required></label>
    <div></div>
    <label class="block"><span class="text-sm text-slate-300">Effective from</span><input type="date" name="effective_from" value="{{ old('effective_from', isset($priceList) ? $priceList->effective_from?->toDateString() : '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
    <label class="block"><span class="text-sm text-slate-300">Effective to</span><input type="date" name="effective_to" value="{{ old('effective_to', isset($priceList) ? $priceList->effective_to?->toDateString() : '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
    <label class="flex items-center gap-3 md:col-span-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $priceList->is_active ?? true))><span>Active price list</span></label>
</div>
