<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Customer visits</h1>
        <p class="mt-1 text-sm text-slate-400">Check-ins, geofence results, outcomes and suspicious activity review.</p>
    </div>

    <form class="mb-5 flex flex-wrap gap-3">
        <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All statuses</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="completed" @selected($status === 'completed')>Completed</option>
        </select>
        <label class="flex items-center gap-2 rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <input type="checkbox" name="flagged" value="1" @checked($flagged)>
            Unreviewed flags only
        </label>
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5"><tr><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Check-in</th><th class="px-5 py-3">Outcome</th><th class="px-5 py-3">Flags</th><th></th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($visits as $visit)
                    <tr>
                        <td class="px-5 py-4">{{ $visit->customer?->name ?? 'Deleted customer' }}</td>
                        <td class="px-5 py-4">{{ $visit->salesman?->full_name ?? $visit->salesman?->user?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $visit->is_planned ? 'Planned' : 'Unplanned' }}</td>
                        <td class="px-5 py-4">{{ $visit->checked_in_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-4">{{ $visit->outcome ? str($visit->outcome)->replace('_', ' ')->title() : ucfirst($visit->status) }}</td>
                        <td class="px-5 py-4">{{ $visit->suspicious_flags_count ?: '—' }}</td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.visits.show', $visit) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No visits found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $visits->links() }}</div>
</x-layouts.app>
