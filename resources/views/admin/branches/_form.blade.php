<div class="grid gap-5 md:grid-cols-2">
    <label class="block">
        <span class="text-sm text-slate-300">Branch name</span>
        <input name="name" value="{{ old('name', $branch->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Code</span>
        <input name="code" value="{{ old('code', $branch->code ?? '') }}" placeholder="KBL" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase" required>
    </label>

    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">Zone geofence polygon JSON <span class="text-slate-500">(optional)</span></span>
        <textarea name="geofence_polygon" rows="5" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 font-mono text-xs" placeholder="[[34.50,69.10],[34.60,69.10],[34.60,69.20],[34.50,69.20]]">{{ old('geofence_polygon', isset($branch) && $branch->geofence_polygon ? json_encode($branch->geofence_polygon) : '') }}</textarea>
        <span class="mt-1 block text-xs text-slate-500">Latitude/longitude pairs used to define the operational zone boundary.</span>
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
