@php($editing = isset($assignment))
<div class="grid gap-5 md:grid-cols-2">
    @unless($editing)
        <label class="block md:col-span-2">
            <span class="text-sm text-slate-300">Salesman</span>
            <select name="salesman_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                <option value="">Select salesman</option>
                @foreach($salesmen as $salesman)
                    <option value="{{ $salesman->id }}" @selected((string) old('salesman_id') === (string) $salesman->id)>{{ $salesman->employee_code }} — {{ $salesman->full_name }}</option>
                @endforeach
            </select>
        </label>
    @endunless

    <label class="block">
        <span class="text-sm text-slate-300">Branch</span>
        <select name="branch_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Unassigned</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $assignment->branch_id ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Supervisor</span>
        <select name="supervisor_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Unassigned</option>
            @foreach($supervisors as $supervisor)
                <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id', $assignment->supervisor_id ?? '') === (string) $supervisor->id)>{{ $supervisor->employee_code }} — {{ $supervisor->full_name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Territory</span>
        <select name="territory_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Unassigned</option>
            @foreach($territories as $territory)
                <option value="{{ $territory->id }}" @selected((string) old('territory_id', $assignment->territory_id ?? '') === (string) $territory->id)>{{ $territory->code }} — {{ $territory->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Route</span>
        <select name="route_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Unassigned</option>
            @foreach($routes as $route)
                <option value="{{ $route->id }}" @selected((string) old('route_id', $assignment->route_id ?? '') === (string) $route->id)>
                    {{ $route->code }} — {{ $route->name }}{{ $route->territory ? ' / '.$route->territory->name : '' }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Effective from</span>
        <input type="date" name="effective_from" value="{{ old('effective_from', isset($assignment) ? $assignment->effective_from->toDateString() : today()->toDateString()) }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Effective to</span>
        <input type="date" name="effective_to" value="{{ old('effective_to', isset($assignment) ? $assignment->effective_to?->toDateString() : '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
        <span class="mt-1 block text-xs text-slate-500">Leave blank for an open/current assignment.</span>
    </label>
</div>
