<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Roles & permissions</h1>
            <p class="mt-1 text-sm text-slate-400">System roles are protected. Create custom roles when your company needs a different permission set.</p>
        </div>
        @if(auth()->user()->hasPermission('roles:manage'))
            <a href="{{ route('admin.roles.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">Create custom role</a>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach($roles as $role)
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold">{{ $role->name }}</h2>
                            @if($role->is_system)
                                <span class="rounded-full bg-blue-500/10 px-2 py-1 text-xs text-blue-300">System</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-400">{{ $role->slug }} · {{ $role->users_count }} user(s)</p>
                    </div>

                    @if(auth()->user()->hasPermission('roles:manage') && ! $role->is_system)
                        <div class="flex gap-2">
                            <a href="{{ route('admin.roles.edit', $role) }}" class="rounded-lg bg-white/10 px-3 py-2 text-sm hover:bg-white/20">Edit</a>
                            @if($role->users_count === 0)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-300 hover:bg-red-500/20">Delete</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse($role->permissions as $permission)
                        <span class="rounded-lg bg-white/5 px-2.5 py-1 text-xs text-slate-300">{{ $permission->slug }}</span>
                    @empty
                        <span class="text-sm text-slate-500">No permissions assigned.</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
