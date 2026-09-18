@php($editing = isset($salesman))

<div class="grid gap-5 md:grid-cols-2">
    @unless($editing)
        <label class="block md:col-span-2">
            <span class="text-sm text-slate-300">User account</span>
            <select name="user_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                <option value="">Select user</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>
                        {{ $user->name }} — {{ $user->email }}
                    </option>
                @endforeach
            </select>
        </label>
    @endunless

    <label class="block">
        <span class="text-sm text-slate-300">Employee code</span>
        <input name="employee_code" value="{{ old('employee_code', $salesman->employee_code ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <div></div>

    <label class="block">
        <span class="text-sm text-slate-300">First name</span>
        <input name="first_name" value="{{ old('first_name', $salesman->first_name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Last name</span>
        <input name="last_name" value="{{ old('last_name', $salesman->last_name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>

    <label class="flex items-center gap-3 md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="h-5 w-5 rounded" @checked((bool) old('is_active', $salesman->is_active ?? true))>
        <span>
            <span class="block font-medium">Active salesman</span>
            <span class="block text-sm text-slate-400">Inactive profiles cannot register a new mobile work device.</span>
        </span>
    </label>
</div>
