<div class="grid gap-5 md:grid-cols-2">
    <label class="block">
        <span class="text-sm text-slate-300">Branch name</span>
        <input name="name" value="{{ old('name', $branch->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Code</span>
        <input name="code" value="{{ old('code', $branch->code ?? '') }}" placeholder="KBL" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase" required>
    </label>

    <label class="flex items-center gap-3 md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="h-5 w-5 rounded" @checked((bool) old('is_active', $branch->is_active ?? true))>
        <span>
            <span class="block font-medium">Active branch</span>
            <span class="block text-sm text-slate-400">Inactive branches stay in history but cannot be selected for new users.</span>
        </span>
    </label>
</div>
