<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Branches</h1>
            <p class="mt-1 text-sm text-slate-400">Primary company branches used for current/default user membership.</p>
        </div>
        @if(auth()->user()->hasPermission('branches:manage'))
            <a href="{{ route('admin.branches.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">Add branch</a>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-300">
                <tr>
                    <th class="px-5 py-3">Branch</th>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Users</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @forelse($branches as $branch)
                    <tr>
                        <td class="px-5 py-4 font-medium">{{ $branch->name }}</td>
                        <td class="px-5 py-4 text-slate-300">{{ $branch->code }}</td>
                        <td class="px-5 py-4">{{ $branch->users_count }}</td>
                        <td class="px-5 py-4">{{ $branch->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-5 py-4">
                            @if(auth()->user()->hasPermission('branches:manage'))
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.branches.edit', $branch) }}" class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20">Edit</a>
                                    @if($branch->users_count === 0)
                                        <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}" onsubmit="return confirm('Delete this branch?')">
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
                        <td colspan="5" class="px-5 py-10 text-center text-slate-400">No branches found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
