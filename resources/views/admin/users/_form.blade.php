@php
    $editing = isset($managedUser);
    $currentRoleId = old('role_id', $editing ? $managedUser->roles->first()?->id : null);
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">Name</span>
        <input name="name" value="{{ old('name', $managedUser->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">Email</span>
        <input type="email" name="email" value="{{ old('email', $managedUser->email ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Role</span>
        <select name="role_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            <option value="">Select a role</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $currentRoleId === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Primary branch</span>
        <select name="branch_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Unassigned</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $managedUser->branch_id ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Password {{ $editing ? '(leave blank to keep current)' : '' }}</span>
        <input type="password" name="password" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" {{ $editing ? '' : 'required' }}>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Confirm password</span>
        <input type="password" name="password_confirmation" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" {{ $editing ? '' : 'required' }}>
    </label>

    <label class="flex items-center gap-3 md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="h-5 w-5 rounded" @checked((bool) old('is_active', $managedUser->is_active ?? true))>
        <span>
            <span class="block font-medium">Active account</span>
            <span class="block text-sm text-slate-400">Inactive users cannot sign in.</span>
        </span>
    </label>
</div>
