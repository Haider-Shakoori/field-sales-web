<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Organizations</h1>
            <p class="mt-1 text-sm text-slate-400">Provision and manage every company on the platform. Each organization is fully tenant-isolated.</p>
        </div>
        <a href="{{ route('admin.organizations.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">New organization</a>
    </div>

    <section class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Organizations</div>
            <div class="mt-2 text-3xl font-bold">{{ $summary['total'] }}</div>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Active</div>
            <div class="mt-2 text-3xl font-bold text-emerald-300">{{ $summary['active'] }}</div>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Suspended</div>
            <div class="mt-2 text-3xl font-bold {{ $summary['suspended'] > 0 ? 'text-amber-300' : 'text-slate-300' }}">{{ $summary['suspended'] }}</div>
        </div>
    </section>

    <form class="mb-5 mt-6 flex flex-wrap gap-3">
        <input name="search" value="{{ $search }}" placeholder="Search name, slug or contact email" class="min-w-64 flex-1 rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
        <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All statuses</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="suspended" @selected($status === 'suspended')>Suspended</option>
        </select>
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold hover:bg-indigo-400">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">Organization</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Users</th>
                        <th class="px-5 py-3">Salesmen</th>
                        <th class="px-5 py-3">Branches</th>
                        <th class="px-5 py-3">Customers</th>
                        <th class="px-5 py-3">Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($organizations as $organization)
                    <tr>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $organization->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $organization->slug }}@if($organization->contact_email) · {{ $organization->contact_email }}@endif
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs {{ $organization->subscription_status === 'active' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300' }}">
                                {{ str($organization->subscription_status)->title() }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-300">{{ $organization->users_count }}</td>
                        <td class="px-5 py-4 text-slate-300">{{ $organization->salesmen_count }}</td>
                        <td class="px-5 py-4 text-slate-300">{{ $organization->branches_count }}</td>
                        <td class="px-5 py-4 text-slate-300">{{ $organization->customers_count }}</td>
                        <td class="px-5 py-4 text-slate-400">{{ $organization->created_at?->format('Y-m-d') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a href="{{ route('admin.organizations.edit', $organization) }}" class="rounded-lg bg-white/10 px-3 py-2">Edit</a>
                                <form method="POST" action="{{ route('admin.organizations.status', $organization) }}">
                                    @csrf
                                    @method('PATCH')
                                    @if($organization->subscription_status === 'active')
                                        <input type="hidden" name="status" value="suspended">
                                        <button class="rounded-lg bg-amber-500/15 px-3 py-2 text-amber-300 hover:bg-amber-500/25">Suspend</button>
                                    @else
                                        <input type="hidden" name="status" value="active">
                                        <button class="rounded-lg bg-emerald-500/15 px-3 py-2 text-emerald-300 hover:bg-emerald-500/25">Activate</button>
                                    @endif
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">No organizations match the current filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $organizations->links() }}</div>
</x-layouts.app>
