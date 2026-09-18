<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Supervisor assignments</h1><p class="mt-1 text-sm text-slate-400">Historical supervisor coverage by branch.</p></div>
        @if(auth()->user()->hasPermission('sales-team:manage'))<a href="{{ route('admin.supervisor-assignments.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">New assignment</a>@endif
    </div>
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-300"><tr><th class="px-5 py-3">Supervisor</th><th class="px-5 py-3">Branch</th><th class="px-5 py-3">Window</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-white/10">
            @forelse($assignments as $assignment)
                <tr><td class="px-5 py-4">{{ $assignment->supervisor?->full_name }}</td><td class="px-5 py-4">{{ $assignment->branch?->name ?? 'Company-wide' }}</td><td class="px-5 py-4">{{ $assignment->effective_from->toDateString() }} → {{ $assignment->effective_to?->toDateString() ?? 'Open' }}</td><td class="px-5 py-4">{{ $assignment->isCurrent() ? 'Current' : 'Historical' }}</td><td class="px-5 py-4 text-right"><a href="{{ route('admin.supervisor-assignments.show', $assignment) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a></td></tr>
            @empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No supervisor assignments.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $assignments->links() }}</div>
</x-layouts.app>
