<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Users</h1>
            <p class="mt-1 text-sm text-slate-400">Company users, primary branch and assigned role.</p>
        </div>
        @if(auth()->user()->hasPermission('users:manage'))
            <a href="{{ route('admin.users.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">Add user</a>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300">
                    <tr>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Branch</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($users as $managedUser)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-medium">{{ $managedUser->name }}</div>
                                <div class="text-slate-400">{{ $managedUser->email }}</div>
                            </td>
                            <td class="px-5 py-4">{{ $managedUser->roles->first()?->name ?? 'No role' }}</td>
                            <td class="px-5 py-4">{{ $managedUser->branch?->name ?? 'All / unassigned' }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $managedUser->is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">
                                    {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                @if(auth()->user()->hasPermission('users:manage'))
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.users.edit', $managedUser) }}" class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20">Edit</a>
                                        @if(auth()->id() !== $managedUser->id)
                                            <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" onsubmit="return confirm('Delete this user?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg bg-red-500/10 px-3 py-2 text-red-300 hover:bg-red-500/20">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-400">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $users->links() }}</div>
</x-layouts.app>
