@php
    $editing = isset($role);
    $selected = collect(old('permission_ids', $editing ? $role->permissions->pluck('id')->all() : []))
        ->map(fn ($id) => (string) $id);
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <label class="block">
        <span class="text-sm text-slate-300">Role name</span>
        <input name="name" value="{{ old('name', $role->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Slug</span>
        <input name="slug" value="{{ old('slug', $role->slug ?? '') }}" placeholder="regional_manager" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>
</div>

<div class="mt-6 space-y-5">
    <div>
        <h2 class="font-semibold">Permissions</h2>
        <p class="mt-1 text-sm text-slate-400">Authorization uses these assignments, not the legacy users.role mirror.</p>
    </div>

    @foreach($permissions as $group => $groupPermissions)
        <fieldset class="rounded-xl border border-white/10 p-4">
            <legend class="px-2 text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $group ?: 'General' }}</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($groupPermissions as $permission)
                    <label class="flex items-start gap-3 rounded-lg bg-white/5 p-3">
                        <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" class="mt-1 h-4 w-4 rounded" @checked($selected->contains((string) $permission->id))>
                        <span>
                            <span class="block font-medium">{{ $permission->name }}</span>
                            <span class="block text-xs text-slate-400">{{ $permission->slug }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
