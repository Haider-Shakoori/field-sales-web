<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold">{{ $supervisor->full_name }}</h1><p class="mt-1 text-sm text-slate-400">{{ $supervisor->employee_code }} · {{ $supervisor->user?->email }}</p></div>
        @if(auth()->user()->hasPermission('sales-team:manage'))<a href="{{ route('admin.supervisors.edit', $supervisor) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>@endif
    </div>
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Coverage history</h2>
            <div class="mt-4 space-y-3">
                @forelse($supervisor->assignments as $assignment)
                    <a href="{{ route('admin.supervisor-assignments.show', $assignment) }}" class="block rounded-xl bg-slate-950 p-4">
                        {{ $assignment->branch?->name ?? 'Company-wide' }}
                        <div class="mt-1 text-sm text-slate-400">{{ $assignment->effective_from->toDateString() }} → {{ $assignment->effective_to?->toDateString() ?? 'Current' }}</div>
                    </a>
                @empty<p class="text-sm text-slate-400">No coverage assignments.</p>@endforelse
            </div>
        </section>
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Salesmen supervised</h2>
            <div class="mt-4 space-y-3">
                @forelse($supervisor->supervisedSalesmanAssignments as $assignment)
                    <div class="rounded-xl bg-slate-950 p-4">
                        {{ $assignment->salesman?->full_name ?? 'Unknown salesman' }}
                        <div class="mt-1 text-sm text-slate-400">{{ $assignment->effective_from->toDateString() }} → {{ $assignment->effective_to?->toDateString() ?? 'Current' }}</div>
                    </div>
                @empty<p class="text-sm text-slate-400">No salesman assignments.</p>@endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
